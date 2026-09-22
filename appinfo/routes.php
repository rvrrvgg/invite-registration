<?php

declare(strict_types=1);

/**
 * Routes are defined here as a fallback for the traditional router.
 * Controllers primarily use PHP 8 attribute routing (#[FrontpageRoute] etc.),
 * but declaring them here as well keeps compatibility explicit and readable.
 */

return [
    'routes' => [
        // Admin API (CRUD for invites) — protected, admin only.
        ['name' => 'admin#index', 'url' => '/admin/invites', 'verb' => 'GET'],
        ['name' => 'admin#create', 'url' => '/admin/invites', 'verb' => 'POST'],
        ['name' => 'admin#revoke', 'url' => '/admin/invites/{id}/revoke', 'verb' => 'POST'],
        ['name' => 'admin#destroy', 'url' => '/admin/invites/{id}', 'verb' => 'DELETE'],

        // Public registration page + submit
        ['name' => 'register#show', 'url' => '/i/{token}', 'verb' => 'GET'],
        ['name' => 'register#submit', 'url' => '/i/{token}', 'verb' => 'POST'],

        // Public email verification
        ['name' => 'verify#confirm', 'url' => '/verify/{token}', 'verb' => 'GET'],
    ],
];
