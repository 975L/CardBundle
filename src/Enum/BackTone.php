<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// How a back renders its picture, so a variant can be made of its pack's own picture before it has an illustration of its own: the dawn's warm light, the sunset's red, the snow's cold, the storm's dark, the wind's grey, the night's blue, the mist's pale. Drawn by the site's stylesheet, as BackEffect is
enum BackTone: string implements TranslatableInterface
{
    case None = 'none';
    case Dawn = 'dawn';
    case Snow = 'snow';
    case Sunset = 'sunset';
    case Storm = 'storm';
    case Wind = 'wind';
    case Night = 'night';
    case Mist = 'mist';

    // The translation key the back office shows, in the "card" domain
    public function label(): string
    {
        return 'label.back_tone_' . $this->value;
    }

    // Its label, so EasyAdmin shows it translated in the form and in the listing alike
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->label(), [], 'card', $locale);
    }

    // Whether its light is drawn over the picture, on the layer the snow, the wind and the mist effects draw on too (see BackEffect::drawsOverPicture())
    public function drawsOverPicture(): bool
    {
        return \in_array($this, [self::Dawn, self::Sunset, self::Storm, self::Night], true);
    }
}
