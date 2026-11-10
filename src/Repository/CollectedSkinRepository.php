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
use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CollectedSkin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CollectedSkin> */
class CollectedSkinRepository extends ServiceEntityRepository implements HolderStoreInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectedSkin::class);
    }

    // Everything a holder has, oldest first, the export of their data reading it as the collection screens do
    /** @return list<CollectedSkin> */
    public function findByHolder(string $holder): array
    {
        return $this->createQueryBuilder('cs')
            ->addSelect('s')
            ->join('cs.skin', 's')
            ->andWhere('cs.holder = :holder')
            ->setParameter('holder', $holder)
            ->orderBy('cs.createdAt', 'ASC')
            ->addOrderBy('cs.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // The packs a skin was given in, to anyone: a refund still finds a pack its pictures left since
    /** @return list<string> */
    public function findPacksOf(CardSkin $skin): array
    {
        return array_column($this->createQueryBuilder('cs')
            ->select('DISTINCT cs.pack')
            ->andWhere('cs.skin = :skin')
            ->setParameter('skin', $skin)
            ->getQuery()
            ->getScalarResult(), 'pack');
    }

    public function hasHolder(string $holder): bool
    {
        return false !== $this->getEntityManager()->getConnection()->fetchOne('SELECT 1 FROM card_collected_skin WHERE holder = :holder LIMIT 1', ['holder' => $holder]);
    }

    // As the backs, pack by pack: a skin both hold for a pack is dropped from the device, its choice carried to the account first when the account chose none in that pack, and the account's choice wins: the device's is cleared in each pack the account already chose in. One transaction, a move never stopping halfway
    public function moveHolder(string $from, string $to): void
    {
        $this->getEntityManager()->getConnection()->transactional(static function (Connection $connection) use ($from, $to): void {
            // The packs the account chose in, read in a derived table, DISTINCT keeping it materialized: MariaDB refuses a subquery on the table being updated (error 1093)
            $taken = '(SELECT DISTINCT c.pack FROM card_collected_skin c WHERE c.holder = :to AND c.chosen = 1)';
            // The device's choices, one per pack, read in a derived table as well
            $carried = '(SELECT DISTINCT d.skin_id, d.pack FROM card_collected_skin d WHERE d.holder = :from AND d.chosen = 1)';
            $connection->executeStatement(
                'UPDATE card_collected_skin a INNER JOIN ' . $carried . ' carried ON carried.skin_id = a.skin_id AND carried.pack = a.pack LEFT JOIN ' . $taken . ' taken ON taken.pack = a.pack SET a.chosen = 1 WHERE a.holder = :to AND taken.pack IS NULL',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement(
                'DELETE d FROM card_collected_skin d INNER JOIN card_collected_skin a ON a.skin_id = d.skin_id AND a.pack = d.pack AND a.holder = :to WHERE d.holder = :from',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement(
                'UPDATE card_collected_skin d INNER JOIN ' . $taken . ' taken ON taken.pack = d.pack SET d.chosen = 0 WHERE d.holder = :from',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement('UPDATE card_collected_skin SET holder = :to WHERE holder = :from', ['from' => $from, 'to' => $to]);
        });
    }

    public function deleteByHolder(string $holder): void
    {
        $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM card_collected_skin WHERE holder = :holder', ['holder' => $holder]);
    }
}
