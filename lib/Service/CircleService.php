<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Service;

use OCP\IUser;
use Psr\Log\LoggerInterface;

/**
 * Thin wrapper around the Circles ("Teams") app.
 *
 * The Circles app is a required dependency of this app (declared in info.xml),
 * so it is normally always present. Calls are still guarded defensively: if the
 * Circles classes cannot be resolved for any reason, listing returns an empty
 * array and adding a member logs and returns false instead of throwing, so the
 * failure surfaces as a clean user-facing error rather than a crash.
 *
 * We resolve OCA\Circles\CirclesManager lazily via the server container.
 */
class CircleService {
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /** Whether the Circles app is available in this instance. */
    public function isAvailable(): bool {
        return $this->getManager() !== null;
    }

    /**
     * List teams (circles) that can be assigned. Runs in a super session so an
     * admin sees all local circles.
     *
     * @return array<int, array{id: string, displayName: string}>
     */
    public function listCircles(): array {
        $manager = $this->getManager();
        if ($manager === null) {
            return [];
        }

        try {
            $manager->startSuperSession();
            try {
                $probe = new \OCA\Circles\Model\Probes\CircleProbe();
                // Only real, user-facing teams: hide system/backend/hidden ones.
                $probe->filterHiddenCircles();
                $probe->filterBackendCircles();

                $result = [];
                foreach ($manager->getCircles($probe) as $circle) {
                    $result[] = [
                        'id' => $circle->getSingleId(),
                        'displayName' => $circle->getDisplayName(),
                    ];
                }
                return $result;
            } finally {
                $manager->stopSession();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Invite registration: could not list circles', ['exception' => $e]);
            return [];
        }
    }

    /**
     * Add a freshly created user to a circle (team).
     * Returns true on success, false on any failure (which is logged).
     *
     * addMember() needs an *initiator* (the "invitedBy" member). A plain
     * super session has no current user, so Circles receives null and throws
     * "setInvitedBy(): ... null given". We therefore run the call inside an
     * OCC-style session initiated as the circle's owner, who is always allowed
     * to add members and serves as the initiator.
     */
    public function addUserToCircle(string $circleId, IUser $user): bool {
        $manager = $this->getManager();
        if ($manager === null) {
            $this->logger->warning('Invite registration: Circles app not available, cannot add user to team', [
                'circle' => $circleId,
            ]);
            return false;
        }

        try {
            // 1. Look up the circle's owner using a super session.
            $ownerId = null;
            $manager->startSuperSession();
            try {
                $probe = new \OCA\Circles\Model\Probes\CircleProbe();
                $probe->includeSystemCircles();
                $circle = $manager->getCircle($circleId, $probe);
                if ($circle->hasOwner()) {
                    $owner = $circle->getOwner();
                    // The owner is a Member; its user id identifies the local user.
                    $ownerId = $owner->getUserId();
                }
            } finally {
                $manager->stopSession();
            }

            if ($ownerId === null || $ownerId === '') {
                $this->logger->error('Invite registration: circle has no resolvable owner', [
                    'circle' => $circleId,
                ]);
                return false;
            }

            // 2. Start a session initiated as the owner, then add the member.
            $manager->startOccSession($ownerId, \OCA\Circles\Model\Member::TYPE_USER);
            try {
                $federatedUser = $manager->getLocalFederatedUser($user->getUID());
                $manager->addMember($circleId, $federatedUser);
                return true;
            } finally {
                $manager->stopSession();
            }
        } catch (\Throwable $e) {
            $this->logger->error('Invite registration: failed to add user to team', [
                'circle' => $circleId,
                'user' => $user->getUID(),
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Resolve the CirclesManager if the Circles app is installed, else null.
     */
    private function getManager(): ?\OCA\Circles\CirclesManager {
        if (!class_exists(\OCA\Circles\CirclesManager::class)) {
            return null;
        }
        try {
            return \OCP\Server::get(\OCA\Circles\CirclesManager::class);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
