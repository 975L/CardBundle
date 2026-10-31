<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

// A repository built over a stubbed connection, the raw SQL it sends being all that is checked, or over a manager whose queries stop once written, their DQL in the exception
trait RegistryStubTrait
{
    /** @param class-string $class */
    private function registry(Connection $connection, string $class): ManagerRegistry
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata($class));
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return $registry;
    }

    // Every query stopped once written, its DQL thrown in a LogicException
    /** @param class-string $class */
    private function stoppedRegistry(string $class): ManagerRegistry
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata($class));
        $entityManager->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($entityManager));
        $entityManager->method('createQuery')->willReturnCallback(static function (string $dql): never {
            throw new \LogicException($dql);
        });
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return $registry;
    }
}
