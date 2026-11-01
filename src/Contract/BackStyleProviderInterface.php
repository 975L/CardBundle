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
use Symfony\Contracts\Translation\TranslatableInterface;

// The tones and effects a site offers its card backs, which it draws itself: a value becomes the classes "card-back--tone-<value>" and "card-back--<value>" for its stylesheet and its own controller (see templates/components/Back.html.twig). Optional and tagged: without one a back has neither
#[AutoconfigureTag('c975l.card_back_style_provider')]
interface BackStyleProviderInterface
{
    /** @return array<string, string|TranslatableInterface> value (10 characters at most, stored) => name shown in the back office */
    public function getBackTones(): array;

    /** @return array<string, string|TranslatableInterface> value (10 characters at most, stored) => name shown in the back office */
    public function getBackEffects(): array;
}
