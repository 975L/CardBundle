<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Export;

use c975L\CardBundle\Export\PdfItem;
use c975L\CardBundle\Export\SocialItem;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    // A PDF leaves itself and one image per face kept, in the folder named after it
    public function testAPdfLeavesItselfAndItsFaces(): void
    {
        $item = new PdfItem('Construction/Export/fr/heros.pdf', '<p>', ['a001-normal', 'dos', 'a001-shiny', null]);

        $this->assertSame('Construction/Export/fr/heros', $item->imageDirectory());
        $this->assertSame(['Construction/Export/fr/heros.pdf', 'Construction/Export/fr/heros/a001-normal.jpg', 'Construction/Export/fr/heros/dos.jpg', 'Construction/Export/fr/heros/a001-shiny.jpg'], $item->outputs());
    }

    // A PDF item written anywhere but to a PDF is refused at once
    public function testAPdfItemNeedsAPdfPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PdfItem('Construction/heros', '<p>');
    }

    // A social card leaves its image and its video, and says so in its manifest entry
    public function testASocialCardLeavesItsImageAndItsVideo(): void
    {
        $item = new SocialItem('Construction/Cartes/Heros/a001-normal', '<p>', '<p>', 'Construction/Cartes/cartes.json', ['reference' => 'A001']);

        $this->assertSame(['Construction/Cartes/Heros/a001-normal.jpg', 'Construction/Cartes/Heros/a001-normal.mp4'], $item->outputs());
        $this->assertSame(['reference' => 'A001', 'jpg' => 'Construction/Cartes/Heros/a001-normal.jpg', 'mp4' => 'Construction/Cartes/Heros/a001-normal.mp4', 'duration' => 7.0], $item->manifestEntry(7.0));
    }
}
