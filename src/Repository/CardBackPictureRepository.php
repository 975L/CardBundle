<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Repository;

use c975L\CardBundle\Entity\CardBackPicture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CardBackPicture> */
class CardBackPictureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardBackPicture::class);
    }

    // A pack's pictures of the backs, by back id, in one query for a pack's whole list of backs
    /** @return array<int, string> */
    public function findImagesOfPack(string $pack): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.back) AS back', 'p.image')
            ->andWhere('p.pack = :pack')
            ->andWhere('p.image IS NOT NULL')
            ->setParameter('pack', $pack)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'image', 'back');
    }
}
