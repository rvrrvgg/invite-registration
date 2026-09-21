<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Controller;

use OCA\InviteRegistration\AppInfo\Application;
use OCA\InviteRegistration\Exception\RegistrationException;
use OCA\InviteRegistration\Service\RegistrationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Public email-confirmation endpoint. Enabling the account happens here after
 * the user clicks the link in their inbox. Rate-limited against token guessing.
 */
class VerifyController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private RegistrationService $registrationService,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Confirm an account via the emailed token (GET, so #[NoCSRFRequired]).
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[BruteForceProtection(action: 'inviteRegistrationVerify')]
    public function confirm(string $token): TemplateResponse {
        try {
            $this->registrationService->confirm($token);
        } catch (RegistrationException $e) {
            $response = new TemplateResponse(
                Application::APP_ID,
                'verify-result',
                ['success' => false, 'message' => $e->getMessage()],
                TemplateResponse::RENDER_AS_GUEST
            );
            $response->throttle(['action' => 'inviteRegistrationVerify']);
            return $response;
        }

        return new TemplateResponse(
            Application::APP_ID,
            'verify-result',
            [
                'success' => true,
                'loginUrl' => $this->urlGenerator->linkToRouteAbsolute('core.login.showLoginForm'),
            ],
            TemplateResponse::RENDER_AS_GUEST
        );
    }
}
