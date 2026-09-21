<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Exception;

/**
 * Thrown when an invite token is invalid, expired, revoked or exhausted.
 * The message is intentionally generic to avoid token enumeration.
 */
class InviteException extends \RuntimeException {
    public const REASON_INVALID = 'invalid';
    public const REASON_EXPIRED = 'expired';
    public const REASON_EXHAUSTED = 'exhausted';
    public const REASON_REVOKED = 'revoked';

    private string $reason;

    public function __construct(string $reason = self::REASON_INVALID, string $message = '') {
        parent::__construct($message !== '' ? $message : $reason);
        $this->reason = $reason;
    }

    public function getReason(): string {
        return $this->reason;
    }
}
