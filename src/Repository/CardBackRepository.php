<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Repository;

use c975L\CardBundle\Entity\CardBack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CardBack> */
class CardBackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardBack::class);
    }

    // A pack's published variants, in the back office's order, those given no position last - MariaDB would sort them first
    /** @return list<CardBack> */
    public function findPublishedOfPack(string $pack): array
    {
        return $this->createQueryBuilder('b')
            ->addSelect('CASE WHEN b.position IS NULL THEN 1 ELSE 0 END AS HIDDEN unpositioned')
            ->andWhere('b.pack = :pack')
            ->andWhere('b.published = true')
            ->setParameter('pack', $pack)
            ->orderBy('unpositioned')
            ->addOrderBy('b.position')
            ->addOrderBy('b.id')
            ->getQuery()
            ->getResult();
    }

    // A published variant by its slug
    public function findPublishedBySlug(string $slug): ?CardBack
    {
        return $this->findOneBy(['slug' => $slug, 'published' => true]);
    }
}
