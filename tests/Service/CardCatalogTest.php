<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Contract\CardCatalogInterface;
use c975L\CardBundle\Contract\CardPackProviderInterface;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class CardCatalogTest extends TestCase
{
    // A site that has not said stands for its raw keys rather than for an error
    public function testWithoutAnyImplementationTheKeysStandIn(): void
    {
        $catalog = new CardCatalog([], [], $this->createStub(ConfigServiceInterface::class));

        $this->assertSame('GUE 02', $catalog->cardName('GUE 02'));
        $this->assertSame([], $catalog->packs());
    }

    // The first catalogue knowing the card names it, and the packs of every provider are listed, the first one's name winning on a shared key
    public function testTheSitesImplementationsAreAsked(): void
    {
        $silent = $this->createStub(CardCatalogInterface::class);
        $silent->method('getCardName')->willReturn(null);
        $knowing = $this->createStub(CardCatalogInterface::class);
        $knowing->method('getCardName')->willReturn('Athelleen');
        $castles = $this->createStub(CardPackProviderInterface::class);
        $castles->method('getPacks')->willReturn(['hur' => 'Hurlton']);
        $more = $this->createStub(CardPackProviderInterface::class);
        $more->method('getPacks')->willReturn(['hur' => 'Ignored', 'gob' => 'Gobelins']);

        $catalog = new CardCatalog([$silent, $knowing], [$castles, $more], $this->createStub(ConfigServiceInterface::class));

        $this->assertSame('Athelleen', $catalog->cardName('GUE 02'));
        $this->assertSame(['hur' => 'Hurlton', 'gob' => 'Gobelins'], $catalog->packs());
    }

    // The providers are asked once, however many times the packs are read
    public function testThePacksAreAskedOnce(): void
    {
        $castles = $this->createMock(CardPackProviderInterface::class);
        $castles->expects($this->once())->method('getPacks')->willReturn(['hur' => 'Hurlton']);
        $catalog = new CardCatalog([], [$castles], $this->createStub(ConfigServiceInterface::class));

        $catalog->packs();
        $this->assertSame(['hur' => 'Hurlton'], $catalog->packs());
    }

    // A pack named by the site, its key standing in for one the site does not list
    public function testAPackIsNamedOrKeepsItsKey(): void
    {
        $castles = $this->createStub(CardPackProviderInterface::class);
        $castles->method('getPacks')->willReturn(['hur' => 'Hurlton']);
        $catalog = new CardCatalog([], [$castles], $this->createStub(ConfigServiceInterface::class));

        $this->assertSame(['Hurlton', 'gob'], [$catalog->packName('hur'), $catalog->packName('gob')]);
    }

    // The shop's currency, euros when the site set none
    public function testTheCurrencyFallsBackToEuros(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnOnConsecutiveCalls('USD', null);
        $catalog = new CardCatalog([], [], $configService);

        $this->assertSame('USD', $catalog->currency());
        $this->assertSame('EUR', $catalog->currency());
    }
}
