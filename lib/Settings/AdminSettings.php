<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Settings;

use OCA\InviteRegistration\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
    public function __construct(
        private IInitialState $initialState,
        private IAppConfig $appConfig,
        private IGroupManager $groupManager,
    ) {
    }

    public function getForm(): TemplateResponse {
        // Provide the current default group and the list of groups to the
        // build-free admin script via IInitialState (base64-encoded JSON).
        $groups = array_map(
            static fn ($group) => [
                'id' => $group->getGID(),
                'displayName' => $group->getDisplayName(),
            ],
            $this->groupManager->search('')
        );

        $this->initialState->provideInitialState('groups', array_values($groups));
        $this->initialState->provideInitialState(
            'defaultGroup',
            $this->appConfig->getValueString(Application::APP_ID, Application::CONFIG_DEFAULT_GROUP, '')
        );

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
