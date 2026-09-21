<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Service;

use OCA\InviteRegistration\AppInfo\Application;
use OCA\InviteRegistration\Db\Verification;
use OCA\InviteRegistration\Db\VerificationMapper;
use OCA\InviteRegistration\Exception\RegistrationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * Creates real Nextcloud accounts from an accepted invite, then drives the
 * email verification flow. The account is created disabled and only enabled
 * once the user confirms their email address.
 *
 * Passwords are passed straight to Nextcloud's user manager and never stored.
 */
class RegistrationService {
    private const VERIFICATION_TOKEN_LENGTH = 48;
    private const VERIFICATION_VALIDITY_SECONDS = 60 * 60 * 24; // 24h

    public function __construct(
        private IUserManager $userManager,
        private IGroupManager $groupManager,
        private VerificationMapper $verificationMapper,
        private IAppConfig $appConfig,
        private IMailer $mailer,
        private IURLGenerator $urlGenerator,
        private ISecureRandom $secureRandom,
        private IL10N $l10n,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Validate the submitted fields without touching the database.
     *
     * @throws RegistrationException with a user-safe message
     */
    public function validateInput(string $username, string $email, string $password): void {
        if (!preg_match(Application::USERNAME_PATTERN, $username)) {
            throw new RegistrationException(
                $this->l10n->t('The username must be 3-32 characters and may only contain letters, digits, dot, underscore and hyphen.')
            );
        }
        if ($this->userManager->userExists($username)) {
            throw new RegistrationException($this->l10n->t('This username is already taken.'));
        }
        if ($email === '' || !$this->mailer->validateMailAddress($email)) {
            throw new RegistrationException($this->l10n->t('Please enter a valid email address.'));
        }
        $minLength = $this->getMinPasswordLength();
        if (strlen($password) < $minLength) {
            throw new RegistrationException(
                $this->l10n->t('The password must be at least %d characters long.', [$minLength])
            );
        }
    }

    /**
     * Create the (disabled) account, assign the default group, store a
     * verification token and send the confirmation email.
     *
     * @throws RegistrationException on any failure (message is user-safe)
     */
    public function register(string $username, string $email, string $password): IUser {
        $this->validateInput($username, $email, $password);

        try {
            $user = $this->userManager->createUser($username, $password);
        } catch (\Throwable $e) {
            // Nextcloud throws for policy violations (e.g. password policy app).
            $this->logger->warning('Invite registration: createUser failed', ['exception' => $e]);
            throw new RegistrationException(
                $this->l10n->t('The account could not be created: %s', [$e->getMessage()])
            );
        }

        if (!$user instanceof IUser) {
            throw new RegistrationException($this->l10n->t('The account could not be created.'));
        }

        try {
            $user->setEMailAddress($email);
            // Disable until the email is confirmed.
            $user->setEnabled(false);
            $this->assignDefaultGroup($user);
            $verification = $this->createVerification($user->getUID());
            $this->sendVerificationEmail($user, $email, $verification->getToken());
        } catch (RegistrationException $e) {
            // Roll back the freshly created account so the invite can be retried.
            $this->safeDeleteUser($user);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Invite registration: post-create step failed', ['exception' => $e]);
            $this->safeDeleteUser($user);
            throw new RegistrationException(
                $this->l10n->t('The confirmation email could not be sent. Please contact your administrator.')
            );
        }

        return $user;
    }

    /**
     * Confirm an email verification token: enable the account and drop the token.
     *
     * @return string the confirmed user id
     * @throws RegistrationException when the token is invalid or expired
     */
    public function confirm(string $token): string {
        if ($token === '' || strlen($token) > 64) {
            throw new RegistrationException($this->l10n->t('This confirmation link is invalid or has expired.'));
        }

        try {
            $verification = $this->verificationMapper->findByToken($token);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            throw new RegistrationException($this->l10n->t('This confirmation link is invalid or has expired.'));
        }

        if ($verification->isExpired()) {
            // Clean up the expired account + token so the invite is not stuck.
            $this->safeDeleteUserById($verification->getUserId());
            $this->verificationMapper->delete($verification);
            throw new RegistrationException($this->l10n->t('This confirmation link is invalid or has expired.'));
        }

        $user = $this->userManager->get($verification->getUserId());
        if (!$user instanceof IUser) {
            $this->verificationMapper->delete($verification);
            throw new RegistrationException($this->l10n->t('This confirmation link is invalid or has expired.'));
        }

        $user->setEnabled(true);
        $this->verificationMapper->delete($verification);

        return $user->getUID();
    }

    private function assignDefaultGroup(IUser $user): void {
        $groupId = (string)$this->appConfig->getValueString(
            Application::APP_ID,
            Application::CONFIG_DEFAULT_GROUP,
            ''
        );
        if ($groupId === '') {
            return;
        }
        $group = $this->groupManager->get($groupId);
        if ($group === null) {
            $this->logger->warning('Invite registration: configured default group does not exist', ['group' => $groupId]);
            return;
        }
        $group->addUser($user);
    }

    private function createVerification(string $userId): Verification {
        // Replace any stale verification for this user.
        $this->verificationMapper->deleteForUser($userId);

        $now = time();
        $verification = new Verification();
        $verification->setUserId($userId);
        $verification->setToken($this->secureRandom->generate(
            self::VERIFICATION_TOKEN_LENGTH,
            ISecureRandom::CHAR_ALPHANUMERIC
        ));
        $verification->setExpiresAt($now + self::VERIFICATION_VALIDITY_SECONDS);
        $verification->setCreatedAt($now);

        return $this->verificationMapper->insert($verification);
    }

    private function sendVerificationEmail(IUser $user, string $email, string $token): void {
        $link = $this->urlGenerator->linkToRouteAbsolute(
            Application::APP_ID . '.verify.confirm',
            ['token' => $token]
        );

        $subject = $this->l10n->t('Confirm your account');
        $bodyIntro = $this->l10n->t('Hello %s,', [$user->getUID()]);
        $bodyText = $this->l10n->t('Your account has been created. Please confirm your email address to activate it. This link is valid for 24 hours.');

        $emailTemplate = $this->mailer->createEMailTemplate('invite_registration.Verify');
        $emailTemplate->setSubject($subject);
        $emailTemplate->addHeader();
        $emailTemplate->addHeading($this->l10n->t('Confirm your account'));
        $emailTemplate->addBodyText($bodyIntro);
        $emailTemplate->addBodyText($bodyText);
        $emailTemplate->addBodyButton($this->l10n->t('Confirm account'), $link);
        $emailTemplate->addBodyText($this->l10n->t('If the button does not work, copy this link into your browser:'));
        $emailTemplate->addBodyText($link);
        $emailTemplate->addFooter();

        $message = $this->mailer->createMessage();
        $message->setTo([$email => $user->getUID()]);
        $message->useTemplate($emailTemplate);

        $failed = $this->mailer->send($message);
        if (!empty($failed)) {
            throw new RegistrationException(
                $this->l10n->t('The confirmation email could not be sent. Please contact your administrator.')
            );
        }
    }

    private function getMinPasswordLength(): int {
        $configured = $this->appConfig->getValueInt(
            Application::APP_ID,
            Application::CONFIG_MIN_PASSWORD_LENGTH,
            Application::DEFAULT_MIN_PASSWORD_LENGTH
        );
        return max(Application::DEFAULT_MIN_PASSWORD_LENGTH, $configured);
    }

    private function safeDeleteUser(IUser $user): void {
        try {
            $user->delete();
        } catch (\Throwable $e) {
            $this->logger->error('Invite registration: rollback delete failed', ['exception' => $e]);
        }
    }

    private function safeDeleteUserById(string $userId): void {
        $user = $this->userManager->get($userId);
        if ($user instanceof IUser) {
            $this->safeDeleteUser($user);
        }
    }
}
