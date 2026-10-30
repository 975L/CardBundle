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
use c975L\CardBundle\Entity\CollectedBack;
use c975L\CardBundle\Payment\BackBasketItemProvider;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Repository\CollectedBackRepository;
use c975L\CardBundle\Service\BackCollector;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PaymentBundle\Contract\AccountBasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class BackBasketItemProviderTest extends TestCase
{
    use PackCatalogTrait;

    // What has PaymentBundle ask an anonymous visitor to sign in before the coordinates: a back lands on an account
    public function testTheBacksAreSoldToAnAccount(): void
    {
        $this->assertInstanceOf(AccountBasketItemProviderInterface::class, $this->provider(null));
    }

    // Only a back on sale is found, published and priced
    public function testOnlyABackOnSaleIsFound(): void
    {
        $this->assertNotNull($this->provider(null, $this->back())->findItem(1));
        $this->assertNull($this->provider(null, $this->back()->setPrice(null))->findItem(1));
        $this->assertNull($this->provider(null, $this->back()->setPublished(false))->findItem(1));
    }

    // A visitor fills the basket freely, once per back; a removal is never refused
    public function testABackIsAddedOnce(): void
    {
        $provider = $this->provider(null);

        $this->assertNull($provider->validateAddition($this->back(), 1));
        $this->assertSame('label.back_once', $provider->validateAddition($this->back(), 2));
        $this->assertSame('label.back_unavailable', $provider->validateAddition($this->back()->setPrice(null), 1));
        $this->assertNull($provider->validateAddition($this->back()->setPrice(null), 0));
    }

    // A back the account already holds would be paid for nothing
    public function testABackAlreadyHeldIsRefused(): void
    {
        $this->assertSame('label.back_already_held', $this->provider($this->user(), $this->back(), true)->validateAddition($this->back(), 1));
    }

    // The checkout asks for an account, and takes the one signed in since the basket was filled
    public function testTheCheckoutNeedsAnAccount(): void
    {
        $basket = new Basket();
        $this->assertSame('label.sign_in_required', $this->provider(null, $this->back())->validateCheckout($basket, [1 => ['quantity' => 1]]));

        $user = $this->user();
        $this->assertNull($this->provider($user, $this->back())->validateCheckout($basket, [1 => ['quantity' => 1]]));
        $this->assertSame($user, $basket->getUser());
    }

    // A back withdrawn or earned since the basket was filled stops the checkout
    public function testTheCheckoutChecksTheBackAgain(): void
    {
        $basket = new Basket()->setUser($this->user());

        $this->assertSame('label.back_unavailable', $this->provider(null, null)->validateCheckout($basket, [1 => ['quantity' => 1]]));
        $this->assertSame('label.back_already_held', $this->provider(null, $this->back(), true)->validateCheckout($basket, [1 => ['quantity' => 1]]));
    }

    // The line carries the back's price and VAT, under its pack
    public function testTheBasketLineCarriesThePrice(): void
    {
        $data = $this->provider(null)->toBasketData($this->back()->setVat(20.0), 1);

        $this->assertSame(200, $data['total']);
        $this->assertSame(33, $data['totalVat']);
        $this->assertSame('Hurlton', $data['parent']['title']);
        $this->assertSame(BackBasketItemProvider::KIND, $data['type']);
    }

    // Digital content supplied at once, which asks for the waiver of the right of withdrawal
    public function testABackIsDigitalContent(): void
    {
        $this->assertSame(Basket::CONTENT_FLAG_DIGITAL, $this->provider(null)->getContentFlags([]));
    }

    // Once paid, each back is given to the account as "shop"
    public function testAPaidBasketGivesTheBacks(): void
    {
        $back = $this->back();
        $collector = $this->createMock(BackCollector::class);
        $collector->expects($this->once())->method('grant')->with('u7', $back, BackBasketItemProvider::SOURCE, true)->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->provider(null, $back, false, $collector, $logger)->onBasketPaid(new Basket()->setUser($this->user()), [1 => ['quantity' => 1]], []);
    }

    // A back paid but not given - earned in the meantime - is logged for the admin to refund
    public function testAPaidBackNotGivenIsLogged(): void
    {
        $collector = $this->createStub(BackCollector::class);
        $collector->method('grant')->willReturn(false);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->provider(null, $this->back(), false, $collector, $logger)->onBasketPaid(new Basket()->setUser($this->user()), [1 => ['quantity' => 1]], []);
    }

    private function provider(?UserInterface $user, ?CardBack $found = null, bool $held = false, ?BackCollector $collector = null, ?LoggerInterface $logger = null): BackBasketItemProvider
    {
        $backRepository = $this->createStub(CardBackRepository::class);
        $backRepository->method('find')->willReturn($found);

        $collectedRepository = $this->createStub(CollectedBackRepository::class);
        $collectedRepository->method('findOneBy')->willReturn($held && null !== $found ? new CollectedBack('u7', $found, 'quiz') : null);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BackBasketItemProvider($backRepository, $collectedRepository, $collector ?? $this->createStub(BackCollector::class), $this->catalog(), $security, $translator, $logger ?? $this->createStub(LoggerInterface::class));
    }

    private function back(): CardBack
    {
        return new CardBack()->setPack('hur')->setSlug('hurlton-orage')->setName('Orage')->setPrice(200)->setPublished(true);
    }

    private function user(): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getId')->willReturn(7);

        return $user;
    }
}
