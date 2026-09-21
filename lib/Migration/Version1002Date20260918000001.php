<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Step 2 of the `revoked` column repair: re-create it as SMALLINT
 * (0 = active, 1 = revoked) with an integer default, so it no longer rejects
 * writes on PostgreSQL.
 */
class Version1002Date20260918000001 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('invite_reg_invites')) {
            return null;
        }

        $table = $schema->getTable('invite_reg_invites');
        if (!$table->hasColumn('revoked')) {
            $table->addColumn('revoked', Types::SMALLINT, [
                'notnull' => true,
                'default' => 0,
            ]);
        }

        return $schema;
    }
}
