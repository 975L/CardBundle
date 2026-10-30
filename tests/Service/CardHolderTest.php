<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Contract\HolderStoreInterface;
use c975L\CardBundle\Service\CardHolder;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Service\FavoriteService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

class CardHolderTest extends TestCase
{
    private const string TOKEN = '0123456789abcdef0123456789abcdef';

    // A device connected to an account hands every store over - the cards, the backs and the site's own - but a store with nothing of the device's is never written to
    public function testAConnectedDeviceHandsEveryStoreOverToTheAccount(): void
    {
        $cards = $this->store(true);
        $cards->expects($this->once())->method('moveHolder')->with(self::TOKEN, 'u7');
        $backs = $this->store(false);
        $backs->expects($this->never())->method('moveHolder');

        $this->assertSame('u7', $this->holder('u7', [$cards, $backs])->resolve(self::TOKEN));
    }

    // A device with no account keeps its own key, and nothing moves
    public function testADeviceAloneMovesNothing(): void
    {
        $store = $this->createMock(HolderStoreInterface::class);
        $store->expects($this->never())->method('hasHolder');

        $this->assertSame(self::TOKEN, $this->holder(self::TOKEN, [$store])->resolve(self::TOKEN));
    }

    // Something that is no device token is never read as one, even when an account answers the call
    public function testAMalformedTokenMovesNothing(): void
    {
        $store = $this->createMock(HolderStoreInterface::class);
        $store->expects($this->never())->method('hasHolder');

        $this->assertSame('u7', $this->holder('u7', [$store])->resolve('not-a-token'));
    }

    public function testTheKeyOfAnAccount(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getId')->willReturn(42);

        $this->assertSame('u42', CardHolder::ofUser($user));
    }

    // Signed in means one of the site's own accounts, saved already, not whatever other user a firewall may hold
    public function testIsAccountOnlyForAnAccountOfTheSite(): void
    {
        $unsaved = $this->createStub(UserInterface::class);
        $unsaved->method('getId')->willReturn(null);
        $saved = $this->createStub(UserInterface::class);
        $saved->method('getId')->willReturn(42);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null, $this->createStub(SymfonyUserInterface::class), $unsaved, $saved);
        $cardHolder = new CardHolder($this->createStub(FavoriteService::class), $security, []);

        $this->assertFalse($cardHolder->isAccount());
        $this->assertFalse($cardHolder->isAccount());
        $this->assertFalse($cardHolder->isAccount());
        $this->assertTrue($cardHolder->isAccount());
    }

    /** @param list<HolderStoreInterface> $stores */
    private function holder(?string $resolved, array $stores): CardHolder
    {
        $favoriteService = $this->createStub(FavoriteService::class);
        $favoriteService->method('resolveHolder')->willReturn($resolved);

        return new CardHolder($favoriteService, $this->createStub(Security::class), $stores);
    }

    private function store(bool $has): HolderStoreInterface & MockObject
    {
        $store = $this->createMock(HolderStoreInterface::class);
        $store->expects($this->once())->method('hasHolder')->with(self::TOKEN)->willReturn($has);

        return $store;
    }
}
