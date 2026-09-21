<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Creates the two tables used by the app:
 *  - oc_invite_reg_invites  : invitation links
 *  - oc_invite_reg_verify   : pending email verifications
 *
 * No passwords or invitee personal data beyond what Nextcloud stores itself.
 */
class Version1000Date20260917000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('invite_reg_invites')) {
            $table = $schema->createTable('invite_reg_invites');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);
            $table->addColumn('token', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('expires_at', Types::BIGINT, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('max_uses', Types::INTEGER, [
                'notnull' => true,
                'default' => 1,
            ]);
            $table->addColumn('used_count', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
            // Stored as smallint (0 = active, 1 = revoked) rather than a real
            // boolean column: PostgreSQL rejects a bound PHP `false` on a
            // NotNull boolean column ("can not store false"). Integers are safe.
            $table->addColumn('revoked', Types::SMALLINT, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('created_at', Types::BIGINT, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('created_by', Types::STRING, [
                'notnull' => true,
                'length' => 64,
                'default' => '',
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token'], 'invite_reg_token_idx');
            $table->addIndex(['created_at'], 'invite_reg_created_idx');
        }

        if (!$schema->hasTable('invite_reg_verify')) {
            $table = $schema->createTable('invite_reg_verify');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('token', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('expires_at', Types::BIGINT, [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('created_at', Types::BIGINT, [
                'notnull' => true,
                'default' => 0,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token'], 'invite_reg_verify_token_idx');
            $table->addIndex(['user_id'], 'invite_reg_verify_user_idx');
        }

        return $schema;
    }
}
