<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Entity;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CardSkinPicture;
use PHPUnit\Framework\TestCase;

class CardSkinPictureTest extends TestCase
{
    // Its slug is written from its skin and its card's reference as soon as both are set, the reference made a slug, and its file is named after it
    public function testItsSlugIsItsSkinsAndItsCards(): void
    {
        $picture = new CardSkinPicture()->setSkin(new CardSkin()->setSlug('toon'));
        $this->assertNull($picture->getSlug());

        $picture->setReference('GOB 01');
        $this->assertSame('toon-gob-01', $picture->getSlug());
        $this->assertSame(CardSkinPicture::MEDIA_DIRECTORY . '/toon-gob-01', $picture->getVichMediaPath());
    }

    // A picture not finished keeps the slug it was given
    public function testAnUnfinishedPictureKeepsItsSlug(): void
    {
        $picture = new CardSkinPicture()->setSlug('kept')->setReference(' - ');
        $picture->setSlugFromSkinAndReference();

        $this->assertSame('kept', $picture->getSlug());
    }

    public function testItIsNamedByItsSkinAndItsCard(): void
    {
        $this->assertSame('Toon - GOB 01', (string) new CardSkinPicture()->setSkin(new CardSkin()->setName('Toon'))->setReference('GOB 01'));
        $this->assertSame('', (string) new CardSkinPicture());
    }
}
