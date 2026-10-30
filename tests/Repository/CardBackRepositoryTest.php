<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Repository;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Repository\CardBackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

// MariaDB sorts a NULL first, so the order of a pack's backs is checked on the DQL it is read by: the query itself is stopped once written
class CardBackRepositoryTest extends TestCase
{
    // A back given no position comes after those given one, as the back office's numbering means
    public function testTheBacksGivenNoPositionComeLast(): void
    {
        try {
            $this->stoppedRepository()->findPublishedOfPack('hur');
            $this->fail('The query was never written.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('CASE WHEN b.position IS NULL THEN 1 ELSE 0 END AS HIDDEN unpositioned', $exception->getMessage());
            $this->assertStringEndsWith('ORDER BY unpositioned ASC, b.position ASC, b.id ASC', $exception->getMessage());
        }
    }

    // The backs on sale are the published and priced ones, pack by pack, those given no position last
    public function testTheBacksOnSaleArePublishedAndPriced(): void
    {
        try {
            $this->stoppedRepository()->findForSale();
            $this->fail('The query was never written.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('b.published = true AND b.price IS NOT NULL', $exception->getMessage());
            $this->assertStringEndsWith('ORDER BY b.pack ASC, unpositioned ASC, b.position ASC, b.id ASC', $exception->getMessage());
        }
    }

    // A store product id is read back into the slug it was derived from; anything else names no back
    public function testAProductIdIsReadBackIntoItsSlug(): void
    {
        $back = new CardBack()->setSlug('chateau-hurlton-orage');
        $repository = $this->getMockBuilder(CardBackRepository::class)->disableOriginalConstructor()->onlyMethods(['findOneBy'])->getMock();
        $repository->expects($this->exactly(2))->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?CardBack => 'chateau-hurlton-orage' === $criteria['slug'] ? $back : null);

        $this->assertSame($back, $repository->findByProductId('back.chateau_hurlton_orage'));
        $this->assertNull($repository->findByProductId('credits.chateau_hurlton_orage'));
        $this->assertNull($repository->findByProductId('back.unknown'));
    }

    // A repository whose queries stop once written, their DQL in the exception
    private function stoppedRepository(): CardBackRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(CardBack::class));
        $entityManager->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(static function (string $dql): never {
            throw new \LogicException($dql);
        });
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new CardBackRepository($registry);
    }
}
