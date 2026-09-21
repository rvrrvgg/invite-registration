<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Verification>
 */
class VerificationMapper extends QBMapper {
    public const TABLE = 'invite_reg_verify';

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE, Verification::class);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findByToken(string $token): Verification {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
        return $this->findEntity($qb);
    }

    /** Remove any pending verifications for a user (e.g. before creating a new one). */
    public function deleteForUser(string $userId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
        $qb->executeStatement();
    }

    /** Housekeeping: delete verification rows that expired before $before. */
    public function deleteExpired(int $before): int {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->lt('expires_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
        return $qb->executeStatement();
    }
}
