<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Repository;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CardSkinPicture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CardSkinPicture> */
class CardSkinPictureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardSkinPicture::class);
    }

    // A skin's pictures of a pack's cards, by reference, in one query for the pack's whole list of cards
    /** @return array<string, string> */
    public function findImagesOfPack(CardSkin $skin, string $pack): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.reference', 'p.image')
            ->andWhere('p.skin = :skin')
            ->andWhere('p.pack = :pack')
            ->andWhere('p.image IS NOT NULL')
            ->setParameter('skin', $skin)
            ->setParameter('pack', $pack)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'image', 'reference');
    }
}
