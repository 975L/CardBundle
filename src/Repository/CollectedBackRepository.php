<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Repository;

use c975L\CardBundle\Contract\HolderStoreInterface;
use c975L\CardBundle\Entity\CollectedBack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CollectedBack> */
class CollectedBackRepository extends ServiceEntityRepository implements HolderStoreInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectedBack::class);
    }

    // Everything a holder has, oldest first, the export of their data reading it as the collection screens do
    /** @return list<CollectedBack> */
    public function findByHolder(string $holder): array
    {
        return $this->createQueryBuilder('cb')
            ->addSelect('b')
            ->join('cb.back', 'b')
            ->andWhere('cb.holder = :holder')
            ->setParameter('holder', $holder)
            ->orderBy('cb.createdAt', 'ASC')
            ->addOrderBy('cb.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasHolder(string $holder): bool
    {
        return false !== $this->getEntityManager()->getConnection()->fetchOne('SELECT 1 FROM card_collected_back WHERE holder = :holder LIMIT 1', ['holder' => $holder]);
    }

    // A back both hold is dropped from the device, its choice carried to the account first when the account chose nothing for that pack, and the account's choices win: the device's are cleared where the account already chose for that pack. One transaction, a move never stopping halfway
    public function moveHolder(string $from, string $to): void
    {
        $this->getEntityManager()->getConnection()->transactional(static function (Connection $connection) use ($from, $to): void {
            // The packs the account chose for are read in a derived table, DISTINCT keeping it materialized: MariaDB refuses a subquery on the table being updated (error 1093)
            $connection->executeStatement(
                'UPDATE card_collected_back a INNER JOIN card_collected_back d ON d.back_id = a.back_id AND d.holder = :from AND d.chosen = 1 INNER JOIN card_back b ON b.id = a.back_id LEFT JOIN (SELECT DISTINCT cb.pack FROM card_collected_back c INNER JOIN card_back cb ON cb.id = c.back_id WHERE c.holder = :to AND c.chosen = 1) taken ON taken.pack = b.pack SET a.chosen = 1 WHERE a.holder = :to AND taken.pack IS NULL',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement(
                'DELETE d FROM card_collected_back d INNER JOIN card_collected_back a ON a.back_id = d.back_id AND a.holder = :to WHERE d.holder = :from',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement(
                'UPDATE card_collected_back d INNER JOIN card_back db ON db.id = d.back_id INNER JOIN card_collected_back a ON a.holder = :to AND a.chosen = 1 INNER JOIN card_back ab ON ab.id = a.back_id AND ab.pack = db.pack SET d.chosen = 0 WHERE d.holder = :from',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement('UPDATE card_collected_back SET holder = :to WHERE holder = :from', ['from' => $from, 'to' => $to]);
        });
    }

    public function deleteByHolder(string $holder): void
    {
        $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM card_collected_back WHERE holder = :holder', ['holder' => $holder]);
    }
}
