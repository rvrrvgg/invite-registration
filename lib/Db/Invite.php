<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getToken()
 * @method void setToken(string $token)
 * @method int getExpiresAt()
 * @method void setExpiresAt(int $expiresAt)
 * @method int getMaxUses()
 * @method void setMaxUses(int $maxUses)
 * @method int getUsedCount()
 * @method void setUsedCount(int $usedCount)
 * @method int getRevoked()
 * @method void setRevoked(int $revoked)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 */
class Invite extends Entity {
    protected string $token = '';
    protected int $expiresAt = 0;
    protected int $maxUses = 1;
    protected int $usedCount = 0;
    // Stored as a small integer (0 = active, 1 = revoked). We deliberately do
    // NOT use a boolean column: on PostgreSQL a NotNull boolean column rejects
    // a bound PHP `false` value ("can not store false"). Integers avoid that.
    protected int $revoked = 0;
    protected int $createdAt = 0;
    protected string $createdBy = '';

    public function __construct() {
        $this->addType('token', 'string');
        $this->addType('expiresAt', 'integer');
        $this->addType('maxUses', 'integer');
        $this->addType('usedCount', 'integer');
        $this->addType('revoked', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('createdBy', 'string');
    }

    public function isRevoked(): bool {
        return $this->revoked !== 0;
    }

    public function isExpired(): bool {
        return $this->expiresAt > 0 && $this->expiresAt <= time();
    }

    public function isExhausted(): bool {
        return $this->usedCount >= $this->maxUses;
    }

    /** An invite is usable when it is not revoked, not expired and not exhausted. */
    public function isUsable(): bool {
        return !$this->isRevoked() && !$this->isExpired() && !$this->isExhausted();
    }

    public function getStatus(): string {
        if ($this->isRevoked()) {
            return 'revoked';
        }
        if ($this->isExpired()) {
            return 'expired';
        }
        if ($this->isExhausted()) {
            return 'exhausted';
        }
        return 'active';
    }

    /**
     * Serialize for the admin UI. The full link is composed in the controller
     * because entity classes do not have access to the URL generator.
     */
    public function jsonSerializeSafe(): array {
        return [
            'id' => $this->getId(),
            'token' => $this->getToken(),
            'expiresAt' => $this->getExpiresAt(),
            'maxUses' => $this->getMaxUses(),
            'usedCount' => $this->getUsedCount(),
            'revoked' => $this->isRevoked(),
            'createdAt' => $this->getCreatedAt(),
            'createdBy' => $this->getCreatedBy(),
            'status' => $this->getStatus(),
        ];
    }
}
