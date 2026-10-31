<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Enum;

use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use PHPUnit\Framework\TestCase;

class BackValuesTest extends TestCase
{
    // The stored values are what the sites' stylesheets and apps read, and the labels are built from them
    public function testTheStoredValuesAndTheirLabels(): void
    {
        $this->assertSame(['none', 'dawn', 'snow', 'sunset', 'storm', 'wind', 'night', 'mist'], array_column(BackTone::cases(), 'value'));
        $this->assertSame(['none', 'zoom', 'stars', 'embers', 'snow', 'lightning', 'wind', 'mist'], array_column(BackEffect::cases(), 'value'));
        $this->assertSame('label.back_tone_dawn', BackTone::Dawn->label());
        $this->assertSame('label.back_effect_snow', BackEffect::Snow->label());
    }
}
