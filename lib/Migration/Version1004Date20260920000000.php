<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the `circle_id` column so that an invitation link can, alternatively to
 * a normal group, assign the new user to a Team (Circle) from the Circles app.
 *
 * Nullable (no notnull, no default) — same reasoning as group_id: PostgreSQL
 * rejects a NotNull column added with an empty-string default. NULL = no team.
 */
class Version1004Date20260920000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('invite_reg_invites')) {
            return null;
        }

        $table = $schema->getTable('invite_reg_invites');

        if (!$table->hasColumn('circle_id')) {
            $table->addColumn('circle_id', Types::STRING, [
                'notnull' => false,
                'length'  => 64,
            ]);
        }

        return $schema;
    }
}
