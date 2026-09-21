<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Step 1 of the `revoked` column repair: drop the old NotNull BOOLEAN column.
 *
 * On PostgreSQL a bound PHP `false` cannot be stored in a NotNull boolean
 * column ("can not store false"), which broke invite creation. Dropping is
 * safe because no usable rows can exist yet — creating an invite was exactly
 * the operation that failed. The column is re-created as SMALLINT in the next
 * migration step (Version1002...), kept separate so no database merges the
 * drop+add into a type change that would re-trigger the boolean cast.
 */
class Version1001Date20260918000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('invite_reg_invites')) {
            return null;
        }

        $table = $schema->getTable('invite_reg_invites');
        if ($table->hasColumn('revoked')) {
            $table->dropColumn('revoked');
        }

        return $schema;
    }
}
