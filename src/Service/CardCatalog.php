<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Contract\CardCatalogInterface;
use c975L\CardBundle\Contract\CardPackProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// What this bundle reads of the site's catalogue, asked of whatever the site tagged (see CardCatalogInterface, CardPackProviderInterface) - none at all being a site that has not said, where the raw keys stand in
class CardCatalog
{
    /**
     * @param iterable<CardCatalogInterface>      $catalogs
     * @param iterable<CardPackProviderInterface> $packProviders
     */
    public function __construct(
        #[AutowireIterator('c975l.card_catalog')]
        private readonly iterable $catalogs,
        #[AutowireIterator('c975l.card_pack_provider')]
        private readonly iterable $packProviders,
    ) {
    }

    // The card's name, its reference when no catalogue knows it
    public function cardName(string $reference): string
    {
        foreach ($this->catalogs as $catalog) {
            $name = $catalog->getCardName($reference);
            if (null !== $name) {
                return $name;
            }
        }

        return $reference;
    }

    // Every pack a back can be made for, key => name, empty when the site lists none
    /** @return array<string, string> */
    public function packs(): array
    {
        $packs = [];
        foreach ($this->packProviders as $provider) {
            $packs += $provider->getPacks();
        }

        return $packs;
    }
}
