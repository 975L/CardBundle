<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Contract\BackStyleProviderInterface;
use c975L\CardBundle\Entity\CardBack;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

// The tones and effects offered in the back office, "none" first and then those of whatever the site tagged (see BackStyleProviderInterface)
class BackStyles
{
    /** @param iterable<BackStyleProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator('c975l.card_back_style_provider')]
        private readonly iterable $providers,
    ) {
    }

    // Value => name
    /** @return array<string, string|TranslatableInterface> */
    public function tones(): array
    {
        $tones = [CardBack::NO_STYLE => t('label.back_tone_none', [], 'card')];
        foreach ($this->providers as $provider) {
            $tones += $provider->getBackTones();
        }

        return $tones;
    }

    // Value => name
    /** @return array<string, string|TranslatableInterface> */
    public function effects(): array
    {
        $effects = [CardBack::NO_STYLE => t('label.back_effect_none', [], 'card')];
        foreach ($this->providers as $provider) {
            $effects += $provider->getBackEffects();
        }

        return $effects;
    }
}
