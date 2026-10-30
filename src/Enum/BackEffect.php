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

// How a card's back moves, one per back and chosen in the back office: a slow zoom on the picture, or a sky drawn over it. Drawing it is the site's and its app's (La Guilde's card_finish_controller.js), this enum only names it. The list grows when a back needs another
enum BackEffect: string implements TranslatableInterface
{
    case None = 'none';
    case Zoom = 'zoom';
    case Stars = 'stars';
    case Embers = 'embers';
    case Snow = 'snow';
    case Lightning = 'lightning';
    case Wind = 'wind';
    case Mist = 'mist';

    // The translation key the back office shows, in the "card" domain
    public function label(): string
    {
        return 'label.back_effect_' . $this->value;
    }

    // Its label, so EasyAdmin shows it translated in the form and in the listing alike
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->label(), [], 'card', $locale);
    }

    // Whether it is drawn over the picture, on the layer the tones light it on too (see BackTone::drawsOverPicture())
    public function drawsOverPicture(): bool
    {
        return \in_array($this, [self::Snow, self::Wind, self::Mist], true);
    }
}
