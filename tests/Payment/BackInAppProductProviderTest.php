<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Payment;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Payment\BackInAppProductProvider;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Service\BackCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BackInAppProductProviderTest extends TestCase
{
    // Each back on sale is one store product: its derived id, its price, a name and a description in each locale
    public function testEachBackOnSaleIsAProduct(): void
    {
        $products = iterator_to_array($this->provider([$this->back('orage', 'Orage')])->getProducts(), false);

        $this->assertCount(1, $products);
        $this->assertSame('back.orage', $products[0]->productId);
        $this->assertSame(100, $products[0]->price);
        $this->assertSame(['fr', 'en', 'es'], array_keys($products[0]->names));
        $this->assertSame('Dos Orage', $products[0]->names['fr']);
        $this->assertSame('label.back_product_description', $products[0]->descriptions['es']);
    }

    // A name past Apple's 30 characters keeps the variant alone, cut to fit
    public function testANameTooLongForTheStoresKeepsTheVariant(): void
    {
        $products = iterator_to_array($this->provider([$this->back('long', 'Coucher de soleil sur la mer')])->getProducts(), false);

        $this->assertSame('Coucher de soleil sur la mer', $products[0]->names['fr']);
        $this->assertLessThanOrEqual(30, mb_strlen($products[0]->names['fr']));
    }

    // A purchase gives the back under the store's name, a refund takes back only what that store gave
    public function testDeliverAndRevokeGoThroughTheCollectorUnderTheStore(): void
    {
        $back = $this->back('orage', 'Orage');
        $collector = $this->createMock(BackCollector::class);
        $collector->expects($this->once())->method('grant')->with('u1', $back, 'apple', true);
        $collector->expects($this->once())->method('revoke')->with('u1', $back, 'google');
        $provider = $this->provider([$back], $back, $collector);

        $this->assertTrue($provider->supports('back.orage'));
        $provider->deliver('u1', 'back.orage', 'apple');
        $provider->revoke('u1', 'back.orage', 'google');
    }

    // Another provider's product is left alone
    public function testAnUnknownProductIsIgnored(): void
    {
        $collector = $this->createMock(BackCollector::class);
        $collector->expects($this->never())->method('grant');
        $collector->expects($this->never())->method('revoke');
        $provider = $this->provider([], null, $collector);

        $this->assertFalse($provider->supports('credits.ten'));
        $provider->deliver('u1', 'credits.ten', 'apple');
        $provider->revoke('u1', 'credits.ten', 'apple');
    }

    /** @param list<CardBack> $forSale */
    private function provider(array $forSale, ?CardBack $byProductId = null, ?BackCollector $collector = null): BackInAppProductProvider
    {
        $repository = $this->createStub(CardBackRepository::class);
        $repository->method('findForSale')->willReturn($forSale);
        $repository->method('findByProductId')->willReturn($byProductId);

        // The name answered as "Dos" and the variant, the description as its key
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $parameters): string => isset($parameters['%name%']) ? 'Dos ' . $parameters['%name%'] : $key);

        return new BackInAppProductProvider($repository, $collector ?? $this->createStub(BackCollector::class), $translator);
    }

    private function back(string $slug, string $name): CardBack
    {
        return new CardBack()->setSlug($slug)->setName($name)->setPrice(100)->setPublished(true);
    }
}
