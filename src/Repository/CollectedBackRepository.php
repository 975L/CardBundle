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

    // A back both hold is dropped from the device, its choice carried to the account first when the account chose none, and the account's choice wins: the device's is cleared where the account already chose one. One transaction, a move never stopping halfway
    public function moveHolder(string $from, string $to): void
    {
        $this->getEntityManager()->getConnection()->transactional(static function (Connection $connection) use ($from, $to): void {
            // Whether the account chose is read in a derived table, DISTINCT keeping it materialized: MariaDB refuses a subquery on the table being updated (error 1093)
            $taken = '(SELECT DISTINCT c.holder FROM card_collected_back c WHERE c.holder = :to AND c.chosen = 1)';
            // One back carried at most, a device that chose one per pack before v1.3 keeping several: the highest id of those both hold, read in a derived table as well
            $carried = '(SELECT MAX(d.back_id) AS back_id FROM card_collected_back d INNER JOIN card_collected_back h ON h.back_id = d.back_id AND h.holder = :to WHERE d.holder = :from AND d.chosen = 1)';
            $connection->executeStatement(
                'UPDATE card_collected_back a INNER JOIN ' . $carried . ' carried ON carried.back_id = a.back_id LEFT JOIN ' . $taken . ' taken ON taken.holder = a.holder SET a.chosen = 1 WHERE a.holder = :to AND taken.holder IS NULL',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement(
                'DELETE d FROM card_collected_back d INNER JOIN card_collected_back a ON a.back_id = d.back_id AND a.holder = :to WHERE d.holder = :from',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement(
                'UPDATE card_collected_back d INNER JOIN ' . $taken . ' taken ON taken.holder = :to SET d.chosen = 0 WHERE d.holder = :from',
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
