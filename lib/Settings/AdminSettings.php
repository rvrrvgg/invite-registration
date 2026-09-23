<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Settings;

use OCA\InviteRegistration\AppInfo\Application;
use OCA\InviteRegistration\Service\CircleService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
    public function __construct(
        private IInitialState $initialState,
        private IGroupManager $groupManager,
        private CircleService $circleService,
    ) {
    }

    public function getForm(): TemplateResponse {
        // Provide the list of groups to the build-free admin script via
        // IInitialState (base64-encoded JSON). The script uses these both for
        // the per-link team selector and to label groups in the invite table.
        $groups = array_map(
            static fn ($group) => [
                'id' => $group->getGID(),
                'displayName' => $group->getDisplayName(),
            ],
            $this->groupManager->search('')
        );

        $this->initialState->provideInitialState('groups', array_values($groups));

        // Teams (Circles) — empty array if the Circles app is not installed.
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
