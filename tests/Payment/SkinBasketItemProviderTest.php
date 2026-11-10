<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Payment;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CardSkinPicture;
use c975L\CardBundle\Payment\SkinBasketItemProvider;
use c975L\CardBundle\Payment\SkinItem;
use c975L\CardBundle\Repository\CardSkinRepository;
use c975L\CardBundle\Service\SkinCollector;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PaymentBundle\Entity\Basket;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class SkinBasketItemProviderTest extends TestCase
{
    use PackCatalogTrait;

    // An item is a skin for one pack, found by its store product id while on sale there
    public function testAnItemIsASkinForOnePackOnSale(): void
    {
        $skin = $this->skin();
        $item = $this->provider([$skin, 'hur'])->findItem('skin.toon.hur');

        $this->assertInstanceOf(SkinItem::class, $item);
        $this->assertSame([$skin, 'hur', 'skin.toon.hur'], [$item->skin, $item->pack, $item->getId()]);
        $this->assertNull($this->provider([$skin->setPublished(false), 'hur'])->findItem('skin.toon.hur'));
        $this->assertNull($this->provider(null)->findItem('skin.unknown_hur'));
    }

    // Asked twice, or already held for that pack, it is refused as the basket fills; a removal never is
    public function testAdditionRefusesTwiceOrAlreadyHeld(): void
    {
        $item = new SkinItem($this->skin(), 'hur');

        $this->assertNull($this->provider(null)->validateAddition($item, 1));
        $this->assertNull($this->provider(null)->validateAddition($item, 0));
        $this->assertSame('label.skin_once', $this->provider(null)->validateAddition($item, 2));
        $this->assertSame('label.skin_already_held', $this->provider(null, true)->validateAddition($item, 1));
    }

    // Stored under the skins' heading, named with its pack, VAT included in its price
    public function testTheBasketDataNamesTheSkinAndItsPack(): void
    {
        $data = $this->provider(null)->toBasketData(new SkinItem($this->skin(), 'hur'), 1);

        $this->assertSame(['skin.toon.hur', 'Toon - Hurlton', 100], [$data['item']['id'], $data['item']['title'], $data['total']]);
        $this->assertSame(SkinBasketItemProvider::KIND, $data['type']);
        $this->assertSame('label.card_skins', $data['parent']['title']);
    }

    // A skin withdrawn, asked twice or earned since the basket was filled stops the checkout, which lands on the account signed in
    public function testTheCheckoutChecksTheSkinAgain(): void
    {
        $basket = new Basket();
        $line = ['skin.toon.hur' => ['quantity' => 1]];

        $this->assertNull($this->provider([$this->skin(), 'hur'])->validateCheckout($basket, $line));
        $this->assertNotNull($basket->getUser());
        $this->assertSame('label.skin_unavailable', $this->provider(null)->validateCheckout($basket, $line));
        $this->assertSame('label.skin_once', $this->provider([$this->skin(), 'hur'])->validateCheckout($basket, ['skin.toon.hur' => ['quantity' => 2]]));
        $this->assertSame('label.skin_already_held', $this->provider([$this->skin(), 'hur'], true)->validateCheckout($basket, $line));
    }

    // Once paid, each skin is given for its pack to the account as "shop"
    public function testAPaidBasketGivesTheSkinForItsPack(): void
    {
        $skin = $this->skin();
        $collector = $this->createMock(SkinCollector::class);
        $collector->expects($this->once())->method('grant')->with('u3', $skin, 'hur', 'shop', true)->willReturn(true);
        $basket = $this->createStub(Basket::class);
        $basket->method('getUser')->willReturn($this->user());

        $this->provider([$skin, 'hur'], false, $collector)->onBasketPaid($basket, ['skin.toon.hur' => ['quantity' => 1]], []);
    }

    // A skin paid but not given is logged under its product id, even one unpriced since
    public function testAPaidSkinNotGivenIsLoggedByItsProductId(): void
    {
        $collector = $this->createStub(SkinCollector::class);
        $collector->method('grant')->willReturn(false);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->anything(), $this->callback(static fn (array $context): bool => 'skin.toon.hur' === $context['skin']));
        $basket = $this->createStub(Basket::class);
        $basket->method('getUser')->willReturn($this->user());

        $this->provider([$this->skin()->setPrice(null), 'hur'], false, $collector, $logger)->onBasketPaid($basket, ['skin.toon.hur' => ['quantity' => 1]], []);
    }

    /** @param array{CardSkin, string}|null $found */
    private function provider(?array $found, bool $held = false, ?SkinCollector $collector = null, ?LoggerInterface $logger = null): SkinBasketItemProvider
    {
        $repository = $this->createStub(CardSkinRepository::class);
        $repository->method('findByProductId')->willReturn($found);
        if (null === $collector) {
            $collector = $this->createStub(SkinCollector::class);
            $collector->method('holds')->willReturn($held);
        }
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->user());
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SkinBasketItemProvider($repository, $collector, $this->catalog(), $security, $translator, $logger ?? $this->createStub(LoggerInterface::class));
    }

    private function user(): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getId')->willReturn(3);

        return $user;
    }

    // A skin on sale at 1,00 €, dressing "hur"
    private function skin(): CardSkin
    {
        $skin = new CardSkin()->setSlug('toon')->setName('Toon')->setPrice(100)->setPublished(true);
        $skin->getPictures()->add(new CardSkinPicture()->setSkin($skin)->setPack('hur')->setReference('HUR 01'));

        return $skin;
    }
}
