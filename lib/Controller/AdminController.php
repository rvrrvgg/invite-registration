<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Controller;

use OCA\InviteRegistration\AppInfo\Application;
use OCA\InviteRegistration\Service\InviteService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * JSON API backing the admin UI. All actions require an admin session; this is
 * enforced by NOT marking them with #[NoAdminRequired] (admin is the default),
 * and by the app's admin settings mount point.
 */
class AdminController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private InviteService $inviteService,
        private IURLGenerator $urlGenerator,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * List all invites, decorated with their absolute link.
     */
    public function index(): DataResponse {
        $invites = array_map(
            fn ($invite) => $this->decorate($invite->jsonSerializeSafe()),
            $this->inviteService->listAll()
        );
        return new DataResponse(['invites' => array_values($invites)]);
    }

    /**
     * Create a new invite.
     * Expects JSON body: { "validityHours": int, "maxUses": int, "groupId": string, "circleId": string }.
     */
    public function create(int $validityHours = 24, int $maxUses = 1, string $groupId = '', string $circleId = ''): DataResponse {
        try {
            $invite = $this->inviteService->create($validityHours, $maxUses, $this->getUid(), $groupId, $circleId);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(
            ['invite' => $this->decorate($invite->jsonSerializeSafe())],
            Http::STATUS_CREATED
        );
    }

    /**
     * Revoke an invite (soft: it stops working but stays in the list).
     */
    public function revoke(int $id): DataResponse {
        try {
            $invite = $this->inviteService->revoke($id);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
        return new DataResponse(['invite' => $this->decorate($invite->jsonSerializeSafe())]);
    }

    /**
     * Permanently delete an invite row.
     */
    public function destroy(int $id): DataResponse {
        try {
            $this->inviteService->delete($id);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
        return new DataResponse([], Http::STATUS_NO_CONTENT);
    }

    /** Add the absolute registration link to a serialized invite. */
    private function decorate(array $data): array {
        $data['link'] = $this->urlGenerator->linkToRouteAbsolute(
            Application::APP_ID . '.register.show',
            ['token' => $data['token']]
        );
        return $data;
    }

    private function getUid(): string {
        $user = $this->userSession->getUser();
        return $user !== null ? $user->getUID() : '';
    }
}
