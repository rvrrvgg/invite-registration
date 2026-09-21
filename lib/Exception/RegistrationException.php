<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Exception;

/**
 * Thrown when account creation fails for a reason the user can act on
 * (username taken, weak password, invalid email, mail server not configured).
 * These messages ARE safe to show to the registering user.
 */
class RegistrationException extends \RuntimeException {
}
