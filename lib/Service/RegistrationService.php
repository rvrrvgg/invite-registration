<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Service;

use OCA\InviteRegistration\AppInfo\Application;
use OCA\InviteRegistration\Db\Invite;
use OCA\InviteRegistration\Db\Verification;
use OCA\InviteRegistration\Db\VerificationMapper;
use OCA\InviteRegistration\Exception\RegistrationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
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
 * If the invite has a group assigned:
 *  1. The user is added to that group.
 *  2. If no Group Folder with that group exists yet, one is created automatically
 *     with the group's display name as mount point and the group assigned with
 *     full permissions.
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
        if (strlen($password) < Application::DEFAULT_MIN_PASSWORD_LENGTH) {
            throw new RegistrationException(
                $this->l10n->t('The password must be at least %d characters long.', [Application::DEFAULT_MIN_PASSWORD_LENGTH])
            );
        }
    }

    /**
     * Create the (disabled) account, assign the invite's group, ensure a Group
     * Folder exists for that group, store a verification token and send the
     * confirmation email.
     *
     * @throws RegistrationException on any failure (message is user-safe)
     */
    public function register(string $username, string $email, string $password, Invite $invite): IUser {
        $this->validateInput($username, $email, $password);

        try {
            $user = $this->userManager->createUser($username, $password);
        } catch (\Throwable $e) {
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
            $user->setEnabled(false);

            // Assign the invite's group and ensure a Group Folder exists.
            $groupId = $invite->getGroupId();
            if ($groupId !== '') {
                $this->assignGroup($user, $groupId);
                $this->ensureGroupFolder($groupId);
            }

            $verification = $this->createVerification($user->getUID());
            $this->sendVerificationEmail($user, $email, $verification->getToken());
        } catch (RegistrationException $e) {
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

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function assignGroup(IUser $user, string $groupId): void {
        $group = $this->groupManager->get($groupId);
        if ($group === null) {
            $this->logger->warning('Invite registration: invite group does not exist', ['group' => $groupId]);
            return;
        }
        $group->addUser($user);
    }

    /**
     * Create a Group Folder for $groupId if none exists yet.
     * Uses the Group Folders app's FolderManager. If the app is not available
     * (e.g. deactivated), the error is logged and silently swallowed so that
     * account creation still succeeds.
     */
    private function ensureGroupFolder(string $groupId): void {
        try {
            $folderManager = \OCP\Server::get(\OCA\GroupFolders\Folder\FolderManager::class);
        } catch (\Throwable $e) {
            $this->logger->warning('Invite registration: Group Folders app not available, skipping folder creation', ['exception' => $e]);
            return;
        }

        try {
            // Check if this group already has a folder assigned.
            if ($folderManager->hasFolderForGroup($groupId)) {
                return;
            }

            // Use the group display name as the mount point, falling back to the ID.
            $group = $this->groupManager->get($groupId);
            $mountPoint = $group !== null ? $group->getDisplayName() : $groupId;

            // If a folder with the same mount point already exists, use the group ID
            // as a suffix to avoid a collision.
            if ($folderManager->mountPointExists($mountPoint)) {
                $mountPoint = $mountPoint . '_' . $groupId;
            }

            $folderId = $folderManager->createFolder($mountPoint);
            $folderManager->addApplicableGroup($folderId, $groupId);

            $this->logger->info('Invite registration: created Group Folder for group', [
                'group'       => $groupId,
                'mountPoint'  => $mountPoint,
                'folderId'    => $folderId,
            ]);
        } catch (\Throwable $e) {
            // A folder creation failure is non-fatal: the user and group
            // assignment succeeded. Log it so an admin can act on it.
            $this->logger->error('Invite registration: failed to create Group Folder', [
                'group'     => $groupId,
                'exception' => $e,
            ]);
        }
    }

    private function createVerification(string $userId): Verification {
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

        $emailTemplate = $this->mailer->createEMailTemplate('invite_registration.Verify');
        $emailTemplate->setSubject($this->l10n->t('Confirm your account'));
        $emailTemplate->addHeader();
        $emailTemplate->addHeading($this->l10n->t('Confirm your account'));
        $emailTemplate->addBodyText($this->l10n->t('Hello %s,', [$user->getUID()]));
        $emailTemplate->addBodyText($this->l10n->t('Your account has been created. Please confirm your email address to activate it. This link is valid for 24 hours.'));
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
