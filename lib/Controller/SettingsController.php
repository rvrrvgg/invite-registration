<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Controller;

use OCA\InviteRegistration\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * Persists the admin-configurable settings (currently: default group).
 * Admin-only (default, no #[NoAdminRequired]).
 */
class SettingsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IAppConfig $appConfig,
        private IGroupManager $groupManager,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Save the default group. An empty string clears the setting (no group).
     * Expects JSON body: { "groupId": string }.
     */
    public function saveDefaultGroup(string $groupId = ''): DataResponse {
        $groupId = trim($groupId);

        if ($groupId !== '' && $this->groupManager->get($groupId) === null) {
            return new DataResponse(
                ['message' => 'Unknown group'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $this->appConfig->setValueString(
            Application::APP_ID,
            Application::CONFIG_DEFAULT_GROUP,
            $groupId
        );

        return new DataResponse(['groupId' => $groupId]);
    }
}
