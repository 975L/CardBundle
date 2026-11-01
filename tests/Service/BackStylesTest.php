<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Contract\BackStyleProviderInterface;
use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Service\BackStyles;
use PHPUnit\Framework\TestCase;

class BackStylesTest extends TestCase
{
    // "None" alone without a provider, the site's values after it with one, a provider never replacing it
    public function testOffersNoneThenWhatTheSiteDraws(): void
    {
        $this->assertSame([CardBack::NO_STYLE], array_keys(new BackStyles([])->tones()));
        $this->assertSame([CardBack::NO_STYLE], array_keys(new BackStyles([])->effects()));

        $provider = $this->createStub(BackStyleProviderInterface::class);
        $provider->method('getBackTones')->willReturn(['dawn' => 'Aube', 'none' => 'Replaced']);
        $provider->method('getBackEffects')->willReturn(['snow' => 'Neige']);
        $styles = new BackStyles([$provider]);

        $this->assertSame(['none', 'dawn'], array_keys($styles->tones()));
        $this->assertNotSame('Replaced', $styles->tones()['none']);
        $this->assertSame(['none', 'snow'], array_keys($styles->effects()));
    }
}
