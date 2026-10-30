<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// The site's own catalogue, which this bundle never stores: a card is named by its reference ("GUE 02", "animaux-12") and whatever it is - a fiche of La Guilde, a card of Papa Câlin - stays the site's entity. Optional and tagged rather than aliased, so a site implementing it has nothing to configure: without one the references are shown as they are (see CardCatalog)
#[AutoconfigureTag('c975l.card_catalog')]
interface CardCatalogInterface
{
    // The card's name, null for a reference the catalogue no longer knows
    public function getCardName(string $reference): ?string;
}
