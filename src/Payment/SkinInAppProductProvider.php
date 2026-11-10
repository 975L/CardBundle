<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Payment;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Repository\CardSkinRepository;
use c975L\CardBundle\Service\CardCatalog;
use c975L\CardBundle\Service\SkinCollector;
use c975L\PaymentBundle\Contract\InAppProduct;
use c975L\PaymentBundle\Contract\InAppProductProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The skins on sale, as the app stores sell them (see InAppProductProviderInterface): one product per skin and pack it dresses, PaymentBundle verifying the purchase and this giving the skin for that pack through SkinCollector under the store's name, and taking it back on a refund
class SkinInAppProductProvider implements InAppProductProviderInterface
{
    // Apple's limits, the tighter of the two stores: what goes past them is refused at publishing
    private const int NAME_LENGTH = 30;
    private const int DESCRIPTION_LENGTH = 45;

    public function __construct(
        private readonly CardSkinRepository $skinRepository,
        private readonly SkinCollector $skinCollector,
        private readonly CardCatalog $cardCatalog,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Every skin on sale in every pack it dresses, named and described in each store locale
    public function getProducts(): iterable
    {
        foreach ($this->skinRepository->findForSale() as $skin) {
            foreach ($skin->getPacks() as $pack) {
                $names = $descriptions = [];
                foreach (array_keys(InAppProduct::STORE_LOCALES) as $locale) {
                    $names[$locale] = $this->name($skin, $pack, $locale);
                    $descriptions[$locale] = mb_substr($this->translator->trans('label.skin_product_description', [], 'card', $locale), 0, self::DESCRIPTION_LENGTH);
                }

                yield new InAppProduct((string) $skin->getProductId($pack), (int) $skin->getPrice(), $names, $descriptions);
            }
        }
    }

    // Whether the product id names a skin in a pack, on sale or not
    public function supports(string $productId): bool
    {
        return null !== $this->skinRepository->findByProductId($productId);
    }

    // Gives the bought skin for its pack under the store's name, even one unpublished since: the purchase is owed, and one that could not be given is logged for the admin, a purchase restored on a holder already holding it excepted
    public function deliver(string $holder, string $productId, string $store): void
    {
        [$skin, $pack] = $this->skinRepository->findByProductId($productId) ?? [null, ''];
        if (null !== $skin && !$this->skinCollector->grant($holder, $skin, $pack, $store, paid: true) && !$this->skinCollector->holds($holder, $skin, $pack)) {
            $this->logger->warning('Card skin {skin} bought on {store} but not given to {holder}', ['skin' => $productId, 'store' => $store, 'holder' => $holder]);
        }
    }

    // Takes back a refunded skin, only as the store gave it
    public function revoke(string $holder, string $productId, string $store): void
    {
        [$skin, $pack] = $this->skinRepository->findByProductId($productId) ?? [null, ''];
        if (null !== $skin) {
            $this->skinCollector->revoke($holder, $skin, $pack, $store);
        }
    }

    // "Skin" with the skin and the pack where they fit, the skin and the pack alone otherwise
    private function name(CardSkin $skin, string $pack, string $locale): string
    {
        $parameters = ['%name%' => $skin->getName(), '%pack%' => $this->cardCatalog->packName($pack)];
        $name = $this->translator->trans('label.skin_product_name', $parameters, 'card', $locale);

        return mb_substr(mb_strlen($name) <= self::NAME_LENGTH ? $name : strtr('%name% - %pack%', $parameters), 0, self::NAME_LENGTH);
    }
}
