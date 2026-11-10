<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Repository;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CardSkinPicture;
use c975L\CardBundle\Entity\CollectedSkin;
use c975L\CardBundle\Repository\CardSkinPictureRepository;
use c975L\CardBundle\Repository\CardSkinRepository;
use c975L\CardBundle\Repository\CollectedSkinRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

// The order of the skins and a pack's pictures are checked on the DQL they are read by, the query stopped once written; a product id against the skins themselves
class CardSkinRepositoryTest extends TestCase
{
    use RegistryStubTrait;

    // A skin given no position comes after those given one, its pictures read in the same query
    public function testTheSkinsGivenNoPositionComeLastWithTheirPictures(): void
    {
        try {
            new CardSkinRepository($this->stoppedRegistry(CardSkin::class))->findForSale();
            $this->fail('The query was never written.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('LEFT JOIN s.pictures p', $exception->getMessage());
            $this->assertStringContainsString('s.published = true AND s.price IS NOT NULL', $exception->getMessage());
            $this->assertStringEndsWith('ORDER BY unpositioned ASC, s.position ASC, s.id ASC', $exception->getMessage());
        }
    }

    // A pack's pictures of one skin, by reference, a row whose upload is missing left out so the card keeps its own
    public function testAPacksImagesAreReadByReference(): void
    {
        try {
            new CardSkinPictureRepository($this->stoppedRegistry(CardSkinPicture::class))->findImagesOfPack(new CardSkin(), 'GOB');
            $this->fail('The query was never written.');
        } catch (\LogicException $exception) {
            $this->assertStringStartsWith('SELECT p.reference, p.image FROM', $exception->getMessage());
            $this->assertStringEndsWith('WHERE p.skin = :skin AND p.pack = :pack AND p.image IS NOT NULL', $exception->getMessage());
        }
    }

    // A product id names a skin, read by its slug, and the pack it was derived for, the pack keeping its own case; anything else names none, another prefix or shape without reading the skins
    public function testAProductIdIsReadBackIntoItsSkinAndPack(): void
    {
        $skin = new CardSkin()->setSlug('toon-ink')->setPrice(100);
        $skin->getPictures()->add(new CardSkinPicture()->setSkin($skin)->setPack('GOB')->setReference('GOB 01'));
        $repository = $this->repositoryReading($skin, [], 2);

        $this->assertSame([$skin, 'GOB'], $repository->findByProductId('skin.toon_ink.gob'));
        $this->assertNull($repository->findByProductId('skin.toon_ink.gue'));
        $this->assertNull($repository->findByProductId('back.toon_ink.gob'));
        $this->assertNull($repository->findByProductId('skin.toon_ink_gob'));
    }

    // A pack whose pictures went since is still named, found among those the skin was given in: a refund reaches it
    public function testAProductIdFindsAPackItsPicturesLeft(): void
    {
        $skin = new CardSkin()->setSlug('toon')->setPrice(100);

        $this->assertSame([$skin, 'Arc_1'], $this->repositoryReading($skin, ['Arc_1'], 1)->findByProductId('skin.toon.arc_1'));
    }

    // The packs a skin was given in, to anyone, in one query
    public function testThePacksASkinWasGivenInAreReadOnce(): void
    {
        try {
            new CollectedSkinRepository($this->stoppedRegistry(CollectedSkin::class))->findPacksOf(new CardSkin());
            $this->fail('The query was never written.');
        } catch (\LogicException $exception) {
            $this->assertStringStartsWith('SELECT DISTINCT cs.pack FROM', $exception->getMessage());
            $this->assertStringEndsWith('WHERE cs.skin = :skin', $exception->getMessage());
        }
    }

    // A repository whose slug query answers $skin and whose held skins were given in $heldPacks
    /** @param list<string> $heldPacks */
    private function repositoryReading(CardSkin $skin, array $heldPacks, int $queries): CardSkinRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('getOneOrNullResult')->willReturn($skin);
        $queryBuilder = $this->createStub(QueryBuilder::class);
        foreach (['addSelect', 'leftJoin', 'andWhere', 'setParameter'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->method('getQuery')->willReturn($query);
        $collected = $this->createStub(CollectedSkinRepository::class);
        $collected->method('findPacksOf')->willReturn($heldPacks);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($collected);
        $repository = $this->getMockBuilder(CardSkinRepository::class)->disableOriginalConstructor()->onlyMethods(['createQueryBuilder', 'getEntityManager'])->getMock();
        $repository->expects($this->exactly($queries))->method('createQueryBuilder')->willReturn($queryBuilder);
        $repository->method('getEntityManager')->willReturn($entityManager);

        return $repository;
    }
}
