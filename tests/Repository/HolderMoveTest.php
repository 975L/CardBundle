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
use c975L\CardBundle\Repository\CollectedSkinRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

// The device-to-account move is raw SQL, MariaDB's multi-table DELETE and UPDATE, which no stub of the ORM would ever run: what is checked is what it says, all of it inside one transaction - for the backs, the device's choice carried to the account first, then the duplicates dropped, then, for the backs, the device's choice cleared where the account chose one, then the key rewritten
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

    // One choice for all the packs: the device's is cleared whenever the account chose one
    public function testTheBacksClearTheDevicesChoiceWhereTheAccountChoseOne(): void
    {
        $statements = $this->statements(CollectedBackRepository::class, 'moveHolder', ['abc', 'u1']);

        $this->assertCount(4, $statements);
        $this->assertStringStartsWith('DELETE d FROM card_collected_back d', $statements[1]);
        $this->assertStringContainsString('(SELECT DISTINCT c.holder FROM card_collected_back c WHERE c.holder = :to AND c.chosen = 1) taken', $statements[2]);
        $this->assertStringEndsWith('taken ON taken.holder = :to SET d.chosen = 0 WHERE d.holder = :from', $statements[2]);
        $this->assertSame('UPDATE card_collected_back SET holder = :to WHERE holder = :from', $statements[3]);
    }

    // A back both hold, chosen on the device only, keeps being chosen: the account takes the choice before the device's row is dropped, unless it chose one already, and takes one at most
    public function testTheBacksCarryTheDevicesChoiceBeforeDroppingTheDuplicates(): void
    {
        $statement = $this->statements(CollectedBackRepository::class, 'moveHolder', ['abc', 'u1'])[0];

        $this->assertStringStartsWith('UPDATE card_collected_back a INNER JOIN (SELECT MAX(d.back_id) AS back_id FROM card_collected_back d', $statement);
        $this->assertStringContainsString('WHERE d.holder = :from AND d.chosen = 1) carried ON carried.back_id = a.back_id', $statement);
        $this->assertStringContainsString('(SELECT DISTINCT c.holder FROM card_collected_back c', $statement);
        $this->assertStringEndsWith('SET a.chosen = 1 WHERE a.holder = :to AND taken.holder IS NULL', $statement);
    }

    // A choice per pack: the device's choice carried to the account in each pack the account chose none in, then the duplicates of the same skin and pack dropped, then the device's choice cleared in each pack the account chose in
    public function testTheSkinsCarryAndClearTheChoicePackByPack(): void
    {
        $statements = $this->statements(CollectedSkinRepository::class, 'moveHolder', ['abc', 'u1']);

        $this->assertCount(4, $statements);
        $this->assertStringContainsString('(SELECT DISTINCT d.skin_id, d.pack FROM card_collected_skin d WHERE d.holder = :from AND d.chosen = 1) carried ON carried.skin_id = a.skin_id AND carried.pack = a.pack', $statements[0]);
        $this->assertStringEndsWith('taken ON taken.pack = a.pack SET a.chosen = 1 WHERE a.holder = :to AND taken.pack IS NULL', $statements[0]);
        $this->assertStringContainsString('ON a.skin_id = d.skin_id AND a.pack = d.pack AND a.holder = :to WHERE d.holder = :from', $statements[1]);
        $this->assertStringEndsWith('taken ON taken.pack = d.pack SET d.chosen = 0 WHERE d.holder = :from', $statements[2]);
        $this->assertSame('UPDATE card_collected_skin SET holder = :to WHERE holder = :from', $statements[3]);
    }

    public function testDeletingAHolderTouchesItsOwnTableOnly(): void
    {
        $this->assertSame(['DELETE FROM card_collected WHERE holder = :holder'], $this->statements(CollectedCardRepository::class, 'deleteByHolder', ['u1']));
        $this->assertSame(['DELETE FROM card_collected_back WHERE holder = :holder'], $this->statements(CollectedBackRepository::class, 'deleteByHolder', ['u1']));
        $this->assertSame(['DELETE FROM card_collected_skin WHERE holder = :holder'], $this->statements(CollectedSkinRepository::class, 'deleteByHolder', ['u1']));
    }

    /**
     * @param class-string<CollectedCardRepository|CollectedBackRepository|CollectedSkinRepository> $class
     * @param list<string>                                                                          $arguments
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
