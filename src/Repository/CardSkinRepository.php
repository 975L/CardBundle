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
use c975L\CardBundle\Entity\CollectedSkin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CardSkin> */
class CardSkinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardSkin::class);
    }

    // The published skins with their pictures, in the back office's order: the packs each dresses read without a query per skin
    /** @return list<CardSkin> */
    public function findPublished(): array
    {
        return $this->published()
            ->getQuery()
            ->getResult();
    }

    // A published skin by its slug
    public function findPublishedBySlug(string $slug): ?CardSkin
    {
        return $this->findOneBy(['slug' => $slug, 'published' => true]);
    }

    // The skins on sale, published and priced, with their pictures, in the back office's order
    /** @return list<CardSkin> */
    public function findForSale(): array
    {
        return $this->published()
            ->andWhere('s.price IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    // The skin and the pack a store product id names (see CardSkin::productIdOf()), whether or not it is still on sale: a refund reaches a skin long after it was withdrawn, and a pack whose pictures went since is found among those it was given in, a pack's key losing its case in the id
    /** @return array{CardSkin, string}|null */
    public function findByProductId(string $productId): ?array
    {
        $parts = explode('.', $productId);
        if (3 !== \count($parts) || CardSkin::PRODUCT_PREFIX !== $parts[0]) {
            return null;
        }

        $skin = $this->withPictures()
            ->andWhere('s.slug = :slug')
            ->setParameter('slug', str_replace('_', '-', $parts[1]))
            ->getQuery()
            ->getOneOrNullResult();
        if (!$skin instanceof CardSkin) {
            return null;
        }

        $pack = $this->packNamed($productId, $skin, $skin->getPacks()) ?? $this->packNamed($productId, $skin, $this->heldPacks($skin));

        return null === $pack ? null : [$skin, $pack];
    }

    // The published skins in the back office's order, those given no position last - MariaDB would sort them first
    private function published(): QueryBuilder
    {
        $queryBuilder = $this->withPictures()
            ->addSelect('CASE WHEN s.position IS NULL THEN 1 ELSE 0 END AS HIDDEN unpositioned')
            ->andWhere('s.published = true');
        foreach (['unpositioned', 's.position', 's.id'] as $order) {
            $queryBuilder->addOrderBy($order);
        }

        return $queryBuilder;
    }

    // The packs a skin was ever given in, read from the held skins
    /** @return list<string> */
    private function heldPacks(CardSkin $skin): array
    {
        $collectedSkinRepository = $this->getEntityManager()->getRepository(CollectedSkin::class);

        return $collectedSkinRepository instanceof CollectedSkinRepository ? $collectedSkinRepository->findPacksOf($skin) : [];
    }

    // The pack, among those given, the product id was derived for
    /** @param list<string> $packs */
    private function packNamed(string $productId, CardSkin $skin, array $packs): ?string
    {
        foreach ($packs as $pack) {
            if ($productId === CardSkin::productIdOf((string) $skin->getSlug(), $pack)) {
                return $pack;
            }
        }

        return null;
    }

    // Every skin with its pictures in the same query
    private function withPictures(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->addSelect('p')
            ->leftJoin('s.pictures', 'p');
    }
}
