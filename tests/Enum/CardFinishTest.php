<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Enum;

use c975L\CardBundle\Enum\CardFinish;
use PHPUnit\Framework\TestCase;

class CardFinishTest extends TestCase
{
    // The rarest wins whatever the order they come in, and none held is no finish at all
    public function testBestIsTheRarest(): void
    {
        $this->assertSame(CardFinish::Magic, CardFinish::best([CardFinish::Magic, CardFinish::Normal, CardFinish::Shiny]));
        $this->assertSame(CardFinish::Shiny, CardFinish::best([CardFinish::Normal, CardFinish::Shiny]));
        $this->assertNull(CardFinish::best([]));
    }

    // Each finish requires the one ranked just below it, the plainest none
    public function testEachFinishRequiresTheOneBelow(): void
    {
        foreach (CardFinish::cases() as $finish) {
            $required = $finish->requires();
            null === $required
                ? $this->assertSame(0, $finish->rank())
                : $this->assertSame($finish->rank() - 1, $required->rank());
        }
    }

    // The values are what both sites stored before the bundle: changing one orphans every holding
    public function testTheStoredValuesAreThoseTheSitesHold(): void
    {
        $this->assertSame(['normal', 'shiny', 'magic'], array_map(static fn (CardFinish $finish): string => $finish->value, CardFinish::cases()));
        $this->assertSame(['●', '◆', '✦'], array_map(static fn (CardFinish $finish): string => $finish->mark(), CardFinish::cases()));
    }
}
