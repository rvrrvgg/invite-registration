<?php

declare(strict_types=1);

namespace OCA\InviteRegistration\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Invite>
 */
class InviteMapper extends QBMapper {
    public const TABLE = 'invite_reg_invites';

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE, Invite::class);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findByToken(string $token): Invite {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
        return $this->findEntity($qb);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findById(int $id): Invite {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * @return Invite[]
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('created_at', 'DESC');
        return $this->findEntities($qb);
    }

    /**
     * Atomically increment the used counter, but only if the invite is still
     * usable (not revoked, not expired, and used_count < max_uses). Returns the
     * number of affected rows: 1 on success, 0 if the invite was consumed
     * concurrently or is otherwise no longer usable. This closes the race
     * window between validation and consumption.
     */
    public function consumeAtomically(int $id, int $now): int {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('used_count', $qb->createFunction('used_count + 1'))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('revoked', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            // expires_at = 0 means "never expires"; otherwise it must be in the future.
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('expires_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
                $qb->expr()->gt('expires_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
            ))
            ->andWhere($qb->expr()->lt('used_count', $qb->createFunction('max_uses')));
        return $qb->executeStatement();
    }
}
