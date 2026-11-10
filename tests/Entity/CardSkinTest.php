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

class CardSkinTest extends TestCase
{
    // The packs it dresses are those its pictures are for, each once, in the order they were given
    public function testItDressesThePacksOfItsPictures(): void
    {
        $skin = $this->skin('toon', ['GOB 01' => 'GOB', 'GOB 02' => 'GOB', 'GUE 01' => 'GUE']);

        $this->assertSame(['GOB', 'GUE'], $skin->getPacks());
        $this->assertTrue($skin->dresses('GOB'));
        $this->assertFalse($skin->dresses('ARC'));
    }

    // One store product per pack, derived from the slug and the pack, never typed, and none for a skin only ever given
    public function testItsProductIdNamesItsSlugAndThePack(): void
    {
        $skin = new CardSkin()->setSlug('toon-ink')->setPrice(100);

        $this->assertSame('skin.toon_ink.gob', $skin->getProductId('GOB'));
        $this->assertSame('skin.toon_ink.arc_1', $skin->getProductId('Arc-1'));
        $this->assertNull($skin->setPrice(null)->getProductId('GOB'));
    }

    // On sale once published and priced
    public function testItIsForSaleOncePublishedAndPriced(): void
    {
        $skin = new CardSkin()->setSlug('toon')->setPrice(100);
        $this->assertFalse($skin->isForSale());

        $this->assertTrue($skin->setPublished(true)->isForSale());
        $this->assertFalse($skin->setPrice(null)->isForSale());
    }

    // Its thumbnail lands under its own directory, named after it
    public function testTheMediaPathIsNamedAfterTheSkin(): void
    {
        $this->assertSame(CardSkin::MEDIA_DIRECTORY . '/toon', new CardSkin()->setSlug('toon')->getVichMediaPath());
    }

    /** @param array<string, string> $pictures reference => pack */
    private function skin(string $slug, array $pictures): CardSkin
    {
        $skin = new CardSkin()->setSlug($slug);
        foreach ($pictures as $reference => $pack) {
            $skin->getPictures()->add(new CardSkinPicture()->setSkin($skin)->setPack($pack)->setReference($reference));
        }

        return $skin;
    }
}
