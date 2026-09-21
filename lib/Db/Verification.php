<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method int getExpiresAt()
 * @method void setExpiresAt(int $expiresAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Verification extends Entity {
    protected string $userId = '';
    protected string $token = '';
    protected int $expiresAt = 0;
    protected int $createdAt = 0;

    public function __construct() {
        $this->addType('userId', 'string');
        $this->addType('token', 'string');
        $this->addType('expiresAt', 'integer');
        $this->addType('createdAt', 'integer');
    }

    public function isExpired(): bool {
        return $this->expiresAt > 0 && $this->expiresAt <= time();
    }
}
