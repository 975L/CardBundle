<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Repository;

use c975L\CardBundle\Entity\CollectedCard;
use c975L\CardBundle\Enum\CardFinish;
use c975L\CardBundle\Repository\CollectedBackRepository;
use c975L\CardBundle\Repository\CollectedCardRepository;
use c975L\CardBundle\Repository\CollectedSkinRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// What CardHolder reads before writing, and what CardCollector refuses a finish by
class HolderReadTest extends TestCase
{
    use RegistryStubTrait;

    // DBAL answers false for no row, which is the only "no": any value found is a holder that has something
    #[DataProvider('repositories')]
    public function testHasHolderIsWhetherARowIsFound(string $class, string $table): void
    {
        $queries = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $parameters) use (&$queries): mixed {
            $queries[] = $sql;

            return 'abc' === $parameters['holder'] ? 1 : false;
        });

        $repository = new $class($this->registry($connection, $class));

        $this->assertTrue($repository->hasHolder('abc'));
        $this->assertFalse($repository->hasHolder('u1'));
        $this->assertStringContainsString('FROM ' . $table . ' WHERE holder = :holder', $queries[0]);
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function repositories(): iterable
    {
        yield 'cards' => [CollectedCardRepository::class, 'card_collected'];
        yield 'backs' => [CollectedBackRepository::class, 'card_collected_back'];
        yield 'skins' => [CollectedSkinRepository::class, 'card_collected_skin'];
    }

    // Several finishes of a card gather under its reference, in the order they were read
    public function testTheFinishesGatherByReference(): void
    {
        $repository = $this->getMockBuilder(CollectedCardRepository::class)->disableOriginalConstructor()->onlyMethods(['findBy'])->getMock();
        $repository->expects($this->once())->method('findBy')->with(['holder' => 'u1'])->willReturn([
            new CollectedCard('u1', 'GUE 02', CardFinish::Normal, 'quiz'),
            new CollectedCard('u1', 'HUR 01', CardFinish::Magic, 'draw'),
            new CollectedCard('u1', 'GUE 02', CardFinish::Shiny, 'quiz'),
        ]);

        $this->assertSame(['GUE 02' => [CardFinish::Normal, CardFinish::Shiny], 'HUR 01' => [CardFinish::Magic]], $repository->finishesByReference('u1'));
    }
}
