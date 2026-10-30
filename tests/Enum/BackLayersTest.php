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

class BackLayersTest extends TestCase
{
    // The layer over the picture is what CardBack::validateLayers() refuses two of: a case moved from one side to the other changes which backs can be saved
    public function testTheLayerOverThePictureIsSharedByTheseOnly(): void
    {
        $this->assertSame([BackTone::Dawn, BackTone::Sunset, BackTone::Storm, BackTone::Night], array_values(array_filter(BackTone::cases(), static fn (BackTone $tone): bool => $tone->drawsOverPicture())));
        $this->assertSame([BackEffect::Snow, BackEffect::Wind, BackEffect::Mist], array_values(array_filter(BackEffect::cases(), static fn (BackEffect $effect): bool => $effect->drawsOverPicture())));
    }

    // None draws nothing, so it never clashes with anything
    public function testNoneDrawsNothing(): void
    {
        $this->assertFalse(BackTone::None->drawsOverPicture());
        $this->assertFalse(BackEffect::None->drawsOverPicture());
    }

    // The stored values are what the sites' stylesheets and apps read, and the labels are built from them
    public function testTheStoredValuesAndTheirLabels(): void
    {
        $this->assertSame(['none', 'dawn', 'snow', 'sunset', 'storm', 'wind', 'night', 'mist'], array_column(BackTone::cases(), 'value'));
        $this->assertSame(['none', 'zoom', 'stars', 'embers', 'snow', 'lightning', 'wind', 'mist'], array_column(BackEffect::cases(), 'value'));
        $this->assertSame('label.back_tone_dawn', BackTone::Dawn->label());
        $this->assertSame('label.back_effect_snow', BackEffect::Snow->label());
    }
}
