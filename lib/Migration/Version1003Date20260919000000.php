<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the `group_id` column to oc_invite_reg_invites so that each invitation
 * link can be tied to a specific Nextcloud group. Empty string means "no group".
 */
class Version1003Date20260919000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('invite_reg_invites')) {
            return null;
        }

        $table = $schema->getTable('invite_reg_invites');

        if (!$table->hasColumn('group_id')) {
            $table->addColumn('group_id', Types::STRING, [
                'notnull' => true,
                'length'  => 64,
                'default' => '',
            ]);
        }

        return $schema;
    }
}
