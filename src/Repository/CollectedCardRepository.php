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
use c975L\CardBundle\Entity\CollectedCard;
use c975L\CardBundle\Enum\CardFinish;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CollectedCard> */
class CollectedCardRepository extends ServiceEntityRepository implements HolderStoreInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectedCard::class);
    }

    // The finishes a holder holds, by card reference
    /** @return array<string, list<CardFinish>> */
    public function finishesByReference(string $holder): array
    {
        $finishes = [];
        foreach ($this->findBy(['holder' => $holder]) as $collected) {
            $finishes[$collected->getReference()][] = $collected->getFinish();
        }

        return $finishes;
    }

    // Everything a holder collected, oldest first, for the export of their data
    /** @return list<CollectedCard> */
    public function findByHolder(string $holder): array
    {
        return $this->findBy(['holder' => $holder], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    public function hasHolder(string $holder): bool
    {
        return false !== $this->getEntityManager()->getConnection()->fetchOne('SELECT 1 FROM card_collected WHERE holder = :holder LIMIT 1', ['holder' => $holder]);
    }

    // A finish both already hold is dropped from the device rather than refused: the account keeps its own, nothing is lost. One transaction, a move never stopping halfway
    public function moveHolder(string $from, string $to): void
    {
        $this->getEntityManager()->getConnection()->transactional(static function (Connection $connection) use ($from, $to): void {
            $connection->executeStatement(
                'DELETE d FROM card_collected d INNER JOIN card_collected a ON a.reference = d.reference AND a.finish = d.finish AND a.holder = :to WHERE d.holder = :from',
                ['from' => $from, 'to' => $to],
            );
            $connection->executeStatement('UPDATE card_collected SET holder = :to WHERE holder = :from', ['from' => $from, 'to' => $to]);
        });
    }

    public function deleteByHolder(string $holder): void
    {
        $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM card_collected WHERE holder = :holder', ['holder' => $holder]);
    }
}
