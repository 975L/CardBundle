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

// The packs a card back can be made for, as the site cuts its catalogue - a castle's twelve cards on La Guilde, a set on Papa Câlin. Optional and tagged, as CardCatalogInterface: without one the pack is typed in the back office
#[AutoconfigureTag('c975l.card_pack_provider')]
interface CardPackProviderInterface
{
    /** @return array<string, string> pack key => name shown in the back office */
    public function getPacks(): array;
}
