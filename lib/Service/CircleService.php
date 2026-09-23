<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Service;

use OCP\IUser;
use Psr\Log\LoggerInterface;

/**
 * Thin wrapper around the Circles ("Teams") app.
 *
 * The Circles app is optional. Every call is guarded so that the rest of the
 * app keeps working if Circles is not installed or disabled: listing returns
 * an empty array, and adding a member logs and returns false instead of
 * throwing.
 *
 * We resolve OCA\Circles\CirclesManager lazily via the server container so
 * there is no hard dependency on the Circles app being present.
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
            // Super session: act with full privileges to add the member.
            $manager->startSuperSession();
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
