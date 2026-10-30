<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Enum;

// The three finishes a card is held in. The finish is no other card: a card in gold is still that card, only seen differently - so a holding is a card and a finish, never a card of its own. What each finish is called and what it adds is the site's (metal and fire on La Guilde), not this enum's
enum CardFinish: string
{
    case Normal = 'normal';
    case Shiny = 'shiny';
    case Magic = 'magic';

    // The sign written after the card's number and under each slot of an album, the same on every site so a finish is read without a word
    public function mark(): string
    {
        return match ($this) {
            self::Normal => '●',
            self::Shiny => '◆',
            self::Magic => '✦',
        };
    }

    // From the plainest to the rarest, the order an album lays them out in
    public function rank(): int
    {
        return match ($this) {
            self::Normal => 0,
            self::Shiny => 1,
            self::Magic => 2,
        };
    }

    // The finish below, for a site that only gives a finish over the one under it (La Guilde) - CardCollector never imposes it, Papa Câlin drawing any finish at random
    public function requires(): ?self
    {
        return match ($this) {
            self::Normal => null,
            self::Shiny => self::Normal,
            self::Magic => self::Shiny,
        };
    }

    // The rarest of those given, the one a card is shown in when several are held, null when none is
    /** @param iterable<self> $finishes */
    public static function best(iterable $finishes): ?self
    {
        $best = null;
        foreach ($finishes as $finish) {
            if (null === $best || $finish->rank() > $best->rank()) {
                $best = $finish;
            }
        }

        return $best;
    }
}
