<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'invite_registration';

    /** Config keys stored via IAppConfig / IConfig app values. */
    public const CONFIG_DEFAULT_GROUP = 'default_group';
    public const CONFIG_MIN_PASSWORD_LENGTH = 'min_password_length';

    /** Default minimum password length enforced by the app on top of NC policy. */
    public const DEFAULT_MIN_PASSWORD_LENGTH = 10;

    /** Username validation pattern. */
    public const USERNAME_PATTERN = '/^[a-zA-Z0-9._-]{3,32}$/';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Services are autowired via constructor injection; no explicit
        // registrations are required for the current class graph.
    }

    public function boot(IBootContext $context): void {
        // Nothing to bootstrap at runtime.
    }
}
