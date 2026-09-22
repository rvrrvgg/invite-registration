<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Service;

use OCA\InviteRegistration\Db\Invite;
use OCA\InviteRegistration\Db\InviteMapper;
use OCA\InviteRegistration\Exception\InviteException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Security\ISecureRandom;

/**
 * Handles creation, validation, listing, revocation and consumption of invites.
 */
class InviteService {
    /** Length of the generated invite token in characters. */
    private const TOKEN_LENGTH = 32;

    /** Hard limits to keep admin input sane. */
    private const MAX_USES_LIMIT = 100000;
    private const MAX_VALIDITY_SECONDS = 60 * 60 * 24 * 365; // 1 year

    public function __construct(
        private InviteMapper $mapper,
        private ISecureRandom $secureRandom,
    ) {
    }

    /**
     * Create a new invite.
     *
     * @param int    $validityHours how long the link stays valid, in hours. 0 = never expires.
     * @param int    $maxUses       how many accounts may be created (>= 1)
     * @param string $createdBy     admin uid
     * @param string $groupId       Nextcloud group ID to assign on registration ('' = none)
     * @throws \InvalidArgumentException on out-of-range input
     */
    public function create(int $validityHours, int $maxUses, string $createdBy, string $groupId = ''): Invite {
        if ($validityHours < 0) {
            throw new \InvalidArgumentException('validityHours must be >= 0');
        }
        $validitySeconds = $validityHours * 3600;
        if ($validitySeconds > self::MAX_VALIDITY_SECONDS) {
            throw new \InvalidArgumentException('validity too long');
        }
        if ($maxUses < 1 || $maxUses > self::MAX_USES_LIMIT) {
            throw new \InvalidArgumentException('maxUses out of range');
        }

        $now = time();
        $invite = new Invite();
        $invite->setToken($this->generateToken());
        // expiresAt = 0 means "never expires".
        $invite->setExpiresAt($validityHours === 0 ? 0 : $now + $validitySeconds);
        $invite->setMaxUses($maxUses);
        $invite->setUsedCount(0);
        $invite->setRevoked(0);
        // Store null (not empty string) when no team is chosen, to match the
        // nullable column and stay consistent on PostgreSQL.
        $invite->setGroupId($groupId !== '' ? $groupId : null);
        $invite->setCreatedAt($now);
        $invite->setCreatedBy($createdBy);

        return $this->mapper->insert($invite);
    }

    /**
     * Look up an invite by token and ensure it is usable.
     * Throws InviteException (with a reason) if not — callers show a generic error.
     *
     * @throws InviteException
     */
    public function requireUsable(string $token): Invite {
        $invite = $this->findByTokenOrFail($token);

        if ($invite->isRevoked()) {
            throw new InviteException(InviteException::REASON_REVOKED);
        }
        if ($invite->isExpired()) {
            throw new InviteException(InviteException::REASON_EXPIRED);
        }
        if ($invite->isExhausted()) {
            throw new InviteException(InviteException::REASON_EXHAUSTED);
        }
        return $invite;
    }

    /**
     * Atomically consume one use of the invite. Must be called right before or
     * after the account is created. Returns true when a use was successfully
     * claimed, false when the invite was consumed concurrently / became invalid.
     */
    public function consume(int $inviteId): bool {
        return $this->mapper->consumeAtomically($inviteId, time()) === 1;
    }

    /**
     * Roll back a consumed use. Used if account creation fails after we already
     * claimed a use, so the invite is not wasted.
     */
    public function releaseUse(int $inviteId): void {
        try {
            $invite = $this->mapper->findById($inviteId);
            if ($invite->getUsedCount() > 0) {
                $invite->setUsedCount($invite->getUsedCount() - 1);
                $this->mapper->update($invite);
            }
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            // Nothing to release.
        }
    }

    /** @return Invite[] */
    public function listAll(): array {
        return $this->mapper->findAll();
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function revoke(int $id): Invite {
        $invite = $this->mapper->findById($id);
        $invite->setRevoked(1);
        return $this->mapper->update($invite);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function delete(int $id): void {
        $invite = $this->mapper->findById($id);
        $this->mapper->delete($invite);
    }

    /**
     * @throws InviteException when the token does not exist
     */
    private function findByTokenOrFail(string $token): Invite {
        if ($token === '' || strlen($token) > 64) {
            throw new InviteException(InviteException::REASON_INVALID);
        }
        try {
            return $this->mapper->findByToken($token);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            throw new InviteException(InviteException::REASON_INVALID);
        }
    }

    private function generateToken(): string {
        return $this->secureRandom->generate(
            self::TOKEN_LENGTH,
            ISecureRandom::CHAR_ALPHANUMERIC
        );
    }
}
