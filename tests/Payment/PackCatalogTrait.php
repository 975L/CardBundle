<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Payment;

use c975L\CardBundle\Contract\CardPackProviderInterface;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

// A catalogue listing one pack, "hur" named "Hurlton", the currency read off it
trait PackCatalogTrait
{
    private function catalog(): CardCatalog
    {
        $packs = $this->createStub(CardPackProviderInterface::class);
        $packs->method('getPacks')->willReturn(['hur' => 'Hurlton']);

        return new CardCatalog([], [$packs], $this->createStub(ConfigServiceInterface::class));
    }
}
