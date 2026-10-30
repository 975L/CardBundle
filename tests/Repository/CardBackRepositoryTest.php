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
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(CardBack::class));
        $entityManager->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(static function (string $dql): never {
            throw new \LogicException($dql);
        });
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        try {
            new CardBackRepository($registry)->findPublishedOfPack('hur');
            $this->fail('The query was never written.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('CASE WHEN b.position IS NULL THEN 1 ELSE 0 END AS HIDDEN unpositioned', $exception->getMessage());
            $this->assertStringEndsWith('ORDER BY unpositioned ASC, b.position ASC, b.id ASC', $exception->getMessage());
        }
    }
}
