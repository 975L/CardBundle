<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Repository;

use c975L\CardBundle\Repository\CollectedBackRepository;
use c975L\CardBundle\Repository\CollectedCardRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

// The device-to-account move is raw SQL, MariaDB's multi-table DELETE and UPDATE, which no stub of the ORM would ever run: what is checked is what it says, all of it inside one transaction - for the backs, the device's choice carried to the account first, then the duplicates dropped, then, for the backs, the device's choice cleared where the account chose for the same pack, then the key rewritten
class HolderMoveTest extends TestCase
{
    use RegistryStubTrait;

    public function testTheCardsDropTheDevicesDuplicatesThenMove(): void
    {
        $statements = $this->statements(CollectedCardRepository::class, 'moveHolder', ['abc', 'u1']);

        $this->assertCount(2, $statements);
        $this->assertStringStartsWith('DELETE d FROM card_collected d', $statements[0]);
        $this->assertStringContainsString('a.reference = d.reference AND a.finish = d.finish', $statements[0]);
        $this->assertSame('UPDATE card_collected SET holder = :to WHERE holder = :from', $statements[1]);
    }

    // The choice is cleared by pack - the one join that changed shape when the castle became a pack key
    public function testTheBacksClearTheDevicesChoiceWhereTheAccountChoseForThePack(): void
    {
        $statements = $this->statements(CollectedBackRepository::class, 'moveHolder', ['abc', 'u1']);

        $this->assertCount(4, $statements);
        $this->assertStringStartsWith('DELETE d FROM card_collected_back d', $statements[1]);
        $this->assertStringContainsString('a.chosen = 1', $statements[2]);
        $this->assertStringContainsString('ab.pack = db.pack', $statements[2]);
        $this->assertStringContainsString('SET d.chosen = 0 WHERE d.holder = :from', $statements[2]);
        $this->assertSame('UPDATE card_collected_back SET holder = :to WHERE holder = :from', $statements[3]);
    }

    // A back both hold, chosen on the device only, keeps being chosen: the account takes the choice before the device's row is dropped, unless it chose for that pack already
    public function testTheBacksCarryTheDevicesChoiceBeforeDroppingTheDuplicates(): void
    {
        $statement = $this->statements(CollectedBackRepository::class, 'moveHolder', ['abc', 'u1'])[0];

        $this->assertStringStartsWith('UPDATE card_collected_back a INNER JOIN card_collected_back d ON d.back_id = a.back_id AND d.holder = :from AND d.chosen = 1', $statement);
        $this->assertStringContainsString('(SELECT DISTINCT cb.pack FROM card_collected_back c', $statement);
        $this->assertStringEndsWith('SET a.chosen = 1 WHERE a.holder = :to AND taken.pack IS NULL', $statement);
    }

    public function testDeletingAHolderTouchesItsOwnTableOnly(): void
    {
        $this->assertSame(['DELETE FROM card_collected WHERE holder = :holder'], $this->statements(CollectedCardRepository::class, 'deleteByHolder', ['u1']));
        $this->assertSame(['DELETE FROM card_collected_back WHERE holder = :holder'], $this->statements(CollectedBackRepository::class, 'deleteByHolder', ['u1']));
    }

    /**
     * @param class-string<CollectedCardRepository|CollectedBackRepository> $class
     * @param list<string>                                                  $arguments
     *
     * @return list<string>
     */
    private function statements(string $class, string $method, array $arguments): array
    {
        $statements = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $move): mixed => $move($connection));

        new $class($this->registry($connection, $class))->$method(...$arguments);

        return $statements;
    }
}
