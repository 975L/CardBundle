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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

// What this bundle reads of the site's catalogue, asked of whatever the site tagged (see CardCatalogInterface, CardPackProviderInterface) - none at all being a site that has not said, where the raw keys stand in
class CardCatalog implements ResetInterface
{
    // Asked of the providers once per request - or per message in a worker (see reset()) - the back office's pack field reading them for every picture
    /** @var array<string, string>|null */
    private ?array $packs = null;

    /**
     * @param iterable<CardCatalogInterface>      $catalogs
     * @param iterable<CardPackProviderInterface> $packProviders
     */
    public function __construct(
        #[AutowireIterator('c975l.card_catalog')]
        private readonly iterable $catalogs,
        #[AutowireIterator('c975l.card_pack_provider')]
        private readonly iterable $packProviders,
        private readonly ConfigServiceInterface $configService,
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
        if (null === $this->packs) {
            $this->packs = [];
            foreach ($this->packProviders as $provider) {
                $this->packs += $provider->getPacks();
            }
        }

        return $this->packs;
    }

    // A pack's name, its key when the site lists no such pack
    public function packName(string $pack): string
    {
        return $this->packs()[$pack] ?? $pack;
    }

    // The currency backs are sold in, the shop's own, euros when the site set none
    public function currency(): string
    {
        return (string) ($this->configService->get('shop-currency') ?: 'EUR');
    }

    // Forgets the packs between two messages of a worker, a pack added since being listed
    public function reset(): void
    {
        $this->packs = null;
    }
}
