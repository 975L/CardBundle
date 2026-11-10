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
use c975L\CardBundle\Payment\SkinInAppProductProvider;
use c975L\CardBundle\Repository\CardSkinRepository;
use c975L\CardBundle\Service\SkinCollector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SkinInAppProductProviderTest extends TestCase
{
    use PackCatalogTrait;

    // One store product per skin on sale and pack it dresses, named with the pack's name where the site lists it, its key otherwise
    public function testEachPackASkinDressesIsAProduct(): void
    {
        $products = iterator_to_array($this->provider([$this->skin('Toon')])->getProducts(), false);

        $this->assertSame(['skin.toon.hur', 'skin.toon.gob'], array_map(static fn ($product): string => $product->productId, $products));
        $this->assertSame(100, $products[0]->price);
        $this->assertSame('Skin Toon Hurlton', $products[0]->names['fr']);
        $this->assertSame('Skin Toon gob', $products[1]->names['en']);
        $this->assertSame('label.skin_product_description', $products[0]->descriptions['es']);
    }

    // A name past Apple's 30 characters keeps the skin and the pack alone, cut to fit
    public function testANameTooLongForTheStoresKeepsTheSkinAndThePack(): void
    {
        $products = iterator_to_array($this->provider([$this->skin('Aquarelle des grands jours')])->getProducts(), false);

        $this->assertSame('Aquarelle des grands jours - H', $products[0]->names['fr']);
    }

    // A purchase gives the skin for its pack under the store's name, a refund takes back only what that store gave
    public function testDeliverAndRevokeGoThroughTheCollectorForThePack(): void
    {
        $skin = $this->skin('Toon');
        $collector = $this->createMock(SkinCollector::class);
        $collector->expects($this->once())->method('grant')->with('u1', $skin, 'gob', 'apple', true);
        $collector->expects($this->once())->method('revoke')->with('u1', $skin, 'gob', 'google');
        $provider = $this->provider([], [$skin, 'gob'], $collector);

        $this->assertTrue($provider->supports('skin.toon.gob'));
        $provider->deliver('u1', 'skin.toon.gob', 'apple');
        $provider->revoke('u1', 'skin.toon.gob', 'google');
    }

    // A purchase that could not be given is logged for the admin, one restored on a holder already holding it is not
    public function testAPurchaseNotGivenIsLogged(): void
    {
        $skin = $this->skin('Toon');
        $collector = $this->createStub(SkinCollector::class);
        $collector->method('grant')->willReturn(false);
        $collector->method('holds')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $provider = $this->provider([], [$skin, 'gob'], $collector, $logger);

        $provider->deliver('u1', 'skin.toon.gob', 'apple');
        $provider->deliver('u1', 'skin.toon.gob', 'apple');
    }

    // Another provider's product is left alone
    public function testAnUnknownProductIsIgnored(): void
    {
        $collector = $this->createMock(SkinCollector::class);
        $collector->expects($this->never())->method('grant');
        $collector->expects($this->never())->method('revoke');
        $provider = $this->provider([], null, $collector);

        $this->assertFalse($provider->supports('back.orage'));
        $provider->deliver('u1', 'back.orage', 'apple');
        $provider->revoke('u1', 'back.orage', 'apple');
    }

    /**
     * @param list<CardSkin>               $forSale
     * @param array{CardSkin, string}|null $byProductId
     */
    private function provider(array $forSale, ?array $byProductId = null, ?SkinCollector $collector = null, ?LoggerInterface $logger = null): SkinInAppProductProvider
    {
        $repository = $this->createStub(CardSkinRepository::class);
        $repository->method('findForSale')->willReturn($forSale);
        $repository->method('findByProductId')->willReturn($byProductId);

        // The name answered as "Skin", the skin and the pack, the description as its key
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $parameters): string => isset($parameters['%name%']) ? 'Skin ' . $parameters['%name%'] . ' ' . $parameters['%pack%'] : $key);

        return new SkinInAppProductProvider($repository, $collector ?? $this->createStub(SkinCollector::class), $this->catalog(), $translator, $logger ?? $this->createStub(LoggerInterface::class));
    }

    // A skin on sale, dressing "hur" (listed by the site as Hurlton) and "gob" (not listed)
    private function skin(string $name): CardSkin
    {
        $skin = new CardSkin()->setSlug('toon')->setName($name)->setPrice(100)->setPublished(true);
        foreach (['HUR 01' => 'hur', 'GOB 01' => 'gob'] as $reference => $pack) {
            $skin->getPictures()->add(new CardSkinPicture()->setSkin($skin)->setPack($pack)->setReference($reference));
        }

        return $skin;
    }
}
