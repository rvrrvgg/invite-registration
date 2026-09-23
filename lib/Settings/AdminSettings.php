<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Settings;

use OCA\InviteRegistration\AppInfo\Application;
use OCA\InviteRegistration\Service\CircleService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
    public function __construct(
        private IInitialState $initialState,
        private CircleService $circleService,
    ) {
    }

    public function getForm(): TemplateResponse {
        // Provide the list of teams (Circles) to the build-free admin script
        // via IInitialState (base64-encoded JSON). Every invite is tied to a
        // team, so this is the only assignment source.
        $this->initialState->provideInitialState('circles', $this->circleService->listCircles());

        Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
        Util::addStyle(Application::APP_ID, 'admin');

        return new TemplateResponse(Application::APP_ID, 'admin-settings');
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    /** Order within the section (only one settings page here). */
    public function getPriority(): int {
        return 10;
    }
}
