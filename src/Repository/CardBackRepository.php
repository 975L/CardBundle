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
use c975L\PaymentBundle\Contract\InAppProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CardBack> */
class CardBackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardBack::class);
    }

    // A pack's published variants, in the back office's order
    /** @return list<CardBack> */
    public function findPublishedOfPack(string $pack): array
    {
        return $this->published()
            ->andWhere('b.pack = :pack')
            ->setParameter('pack', $pack)
            ->getQuery()
            ->getResult();
    }

    // A published variant by its slug
    public function findPublishedBySlug(string $slug): ?CardBack
    {
        return $this->findOneBy(['slug' => $slug, 'published' => true]);
    }

    // The variants on sale, published and priced, pack by pack in the back office's order
    /** @return list<CardBack> */
    public function findForSale(): array
    {
        return $this->published('b.pack')
            ->andWhere('b.price IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    // The variant a store product id names, whether or not it is still on sale: a refund reaches a back long after it was withdrawn. The id is the slug read back (see CardBack::getProductId()), slugs holding hyphens where ids hold underscores
    public function findByProductId(string $productId): ?CardBack
    {
        if (!str_starts_with($productId, CardBack::PRODUCT_PREFIX . '.')) {
            return null;
        }

        $back = $this->findOneBy(['slug' => str_replace('_', '-', substr($productId, \strlen(CardBack::PRODUCT_PREFIX) + 1))]);

        return null !== $back && InAppProduct::idFor(CardBack::PRODUCT_PREFIX, (string) $back->getSlug()) === $productId ? $back : null;
    }

    // The published variants in the back office's order, after the given orders, those given no position last - MariaDB would sort them first
    private function published(string ...$firstOrders): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('b')
            ->addSelect('CASE WHEN b.position IS NULL THEN 1 ELSE 0 END AS HIDDEN unpositioned')
            ->andWhere('b.published = true');
        foreach ([...$firstOrders, 'unpositioned', 'b.position', 'b.id'] as $order) {
            $queryBuilder->addOrderBy($order);
        }

        return $queryBuilder;
    }
}
