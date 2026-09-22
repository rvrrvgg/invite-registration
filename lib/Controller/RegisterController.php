<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Controller;

use OCA\InviteRegistration\AppInfo\Application;
use OCA\InviteRegistration\Exception\InviteException;
use OCA\InviteRegistration\Exception\RegistrationException;
use OCA\InviteRegistration\Service\InviteService;
use OCA\InviteRegistration\Service\RegistrationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Public, invite-gated registration. The registration page is only reachable
 * with a valid token; invalid/expired/revoked/exhausted tokens all render the
 * same generic error to prevent enumeration.
 */
class RegisterController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private InviteService $inviteService,
        private RegistrationService $registrationService,
        private IURLGenerator $urlGenerator,
        private IL10N $l10n,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    /** Absolute URL the registration form posts to. */
    private function submitUrl(string $token): string {
        return $this->urlGenerator->linkToRoute(
            Application::APP_ID . '.register.submit',
            ['token' => $token]
        );
    }

    /**
     * Render the registration form for a given invite token, or a generic
     * error page. Public and rate-limited against token guessing.
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[BruteForceProtection(action: 'inviteRegistrationShow')]
    public function show(string $token): TemplateResponse {
        try {
            $this->inviteService->requireUsable($token);
        } catch (InviteException $e) {
            return $this->errorPage($e);
        }

        $response = new TemplateResponse(
            Application::APP_ID,
            'register',
            [
                'token' => $token,
                'submitUrl' => $this->submitUrl($token),
                'minPasswordLength' => Application::DEFAULT_MIN_PASSWORD_LENGTH,
                'usernamePattern' => Application::USERNAME_PATTERN,
            ],
            TemplateResponse::RENDER_AS_GUEST
        );
        return $response;
    }

    /**
     * Handle the submitted registration form.
     *
     * The security boundary of this public endpoint is the unguessable invite
     * token in the URL, not a browser session. A standard CSRF token cannot be
     * validated reliably on a fresh guest page (no established session), which
     * is what caused "CSRF check failed". We therefore disable the CSRF check
     * here and rely on: (a) the cryptographically random, single-/limited-use,
     * expiring invite token, and (b) brute-force throttling. An attacker cannot
     * forge a useful POST without already knowing the secret token.
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[BruteForceProtection(action: 'inviteRegistrationSubmit')]
    public function submit(
        string $token,
        string $username = '',
        string $email = '',
        string $password = ''
    ): TemplateResponse {
        // 1. The invite must currently be usable.
        try {
            $invite = $this->inviteService->requireUsable($token);
        } catch (InviteException $e) {
            $response = $this->errorPage($e);
            $response->throttle(['action' => 'inviteRegistrationSubmit']);
            return $response;
        }

        // 2. Validate input before claiming a use.
        try {
            $this->registrationService->validateInput($username, $email, $password);
        } catch (RegistrationException $e) {
            return $this->formWithError($token, $e->getMessage(), $username, $email);
        }

        // 3. Atomically claim one use of the invite (closes the race window).
        if (!$this->inviteService->consume($invite->getId())) {
            // Someone else consumed the last use between step 1 and here.
            return $this->errorPage(new InviteException(InviteException::REASON_EXHAUSTED));
        }

        // 4. Create the account + send verification. Release the use on failure.
        try {
            $this->registrationService->register($username, $email, $password, $invite);
        } catch (RegistrationException $e) {
            $this->inviteService->releaseUse($invite->getId());
            return $this->formWithError($token, $e->getMessage(), $username, $email);
        } catch (\Throwable $e) {
            $this->inviteService->releaseUse($invite->getId());
            $this->logger->error('Invite registration failed unexpectedly', ['exception' => $e]);
            return $this->formWithError(
                $token,
                $this->l10n->t('An unexpected error occurred. Please try again later.'),
                $username,
                $email
            );
        }

        // 5. Success — tell the user to check their inbox.
        return new TemplateResponse(
            Application::APP_ID,
            'register-success',
            ['email' => $email],
            TemplateResponse::RENDER_AS_GUEST
        );
    }

    private function formWithError(string $token, string $error, string $username, string $email): TemplateResponse {
        return new TemplateResponse(
            Application::APP_ID,
            'register',
            [
                'token' => $token,
                'submitUrl' => $this->submitUrl($token),
                'minPasswordLength' => Application::DEFAULT_MIN_PASSWORD_LENGTH,
                'usernamePattern' => Application::USERNAME_PATTERN,
                'error' => $error,
                'username' => $username,
                'email' => $email,
            ],
            TemplateResponse::RENDER_AS_GUEST
        );
    }

    private function errorPage(InviteException $e): TemplateResponse {
        // Distinguish only between "expired" and everything-else, both generic.
        $expired = $e->getReason() === InviteException::REASON_EXPIRED;
        $response = new TemplateResponse(
            Application::APP_ID,
            'register-error',
            ['expired' => $expired],
            TemplateResponse::RENDER_AS_GUEST
        );
        $response->throttle(['action' => 'inviteRegistrationShow']);
        return $response;
    }
}
