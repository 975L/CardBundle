<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Payment;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Service\BackCollector;
use c975L\CardBundle\Service\CardHolder;
use c975L\PaymentBundle\Contract\InAppProduct;
use c975L\PaymentBundle\Contract\InAppProductProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The backs on sale, as the app stores sell them (see InAppProductProviderInterface): PaymentBundle verifies the purchase, this gives the back through BackCollector under the store's name, and takes it back on a refund
class BackInAppProductProvider implements InAppProductProviderInterface
{
    // Apple's limits, the tighter of the two stores: what goes past them is refused at publishing
    private const int NAME_LENGTH = 30;
    private const int DESCRIPTION_LENGTH = 45;

    public function __construct(
        private readonly CardBackRepository $backRepository,
        private readonly BackCollector $backCollector,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Every back on sale, named and described in each store locale, one product dressing all the packs
    public function getProducts(): iterable
    {
        foreach ($this->backRepository->findForSale() as $back) {
            $names = $descriptions = [];
            foreach (array_keys(InAppProduct::STORE_LOCALES) as $locale) {
                $names[$locale] = $this->name($back, $locale);
                $descriptions[$locale] = mb_substr($this->translator->trans('label.back_product_description', [], 'card', $locale), 0, self::DESCRIPTION_LENGTH);
            }

            yield new InAppProduct((string) $back->getProductId(), (int) $back->getPrice(), $names, $descriptions);
        }
    }

    // Whether the product id names a back, on sale or not
    public function supports(string $productId): bool
    {
        return null !== $this->backRepository->findByProductId($productId);
    }

    // Gives the bought back under the store's name, even one unpublished since: the purchase is owed
    public function deliver(string $holder, string $productId, string $store): void
    {
        // Bought by an account only: a device's token moves to the account it signs in to, its store purchase then refunded on a key nobody holds any more
        if (!CardHolder::isAccountKey($holder)) {
            $this->logger->warning('Card back {back} bought on {store} by device {holder} and not given: a store purchase needs an account', ['back' => $productId, 'store' => $store, 'holder' => $holder]);

            return;
        }

        $back = $this->backRepository->findByProductId($productId);
        if (null !== $back && !$this->backCollector->grant($holder, $back, $store, paid: true) && !$this->backCollector->holds($holder, $back)) {
            $this->logger->warning('Card back {back} bought on {store} but not given to {holder}', ['back' => $productId, 'store' => $store, 'holder' => $holder]);
        }
    }

    // Takes back a refunded back, only as the store gave it
    public function revoke(string $holder, string $productId, string $store): void
    {
        $back = $this->backRepository->findByProductId($productId);
        if (null !== $back) {
            $this->backCollector->revoke($holder, $back, $store);
        }
    }

    // "Card back" and the variant together where they fit, the variant alone otherwise
    private function name(CardBack $back, string $locale): string
    {
        $name = $this->translator->trans('label.back_product_name', ['%name%' => $back->getName()], 'card', $locale);

        return mb_substr(mb_strlen($name) <= self::NAME_LENGTH ? $name : (string) $back->getName(), 0, self::NAME_LENGTH);
    }
}
