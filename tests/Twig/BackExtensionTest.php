<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Twig;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Repository\CardBackPictureRepository;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Twig\BackExtension;
use PHPUnit\Framework\TestCase;

class BackExtensionTest extends TestCase
{
    // No standard back published: nothing, the site drawing its back as it did
    public function testNoStandardBackGivesNothing(): void
    {
        $this->assertNull($this->extension(null, [])->standard());
    }

    // The standard back as the component draws it, its picture for the pack asked when it has one
    public function testTheStandardBackIsDrawnWithItsPictureForThePack(): void
    {
        $back = new CardBack()->setSlug('papa-calin')->setName('Papa Câlin')->setEffect('stars')->setStandard(true);
        new \ReflectionProperty(CardBack::class, 'id')->setValue($back, 7);

        $this->assertSame(['slug' => 'papa-calin', 'name' => 'Papa Câlin', 'label' => 'Papa Câlin', 'image' => null, 'effect' => 'stars', 'tone' => 'none'], $this->extension($back, [])->standard());
        $this->assertSame('/medias/cards/backs/papa-calin-axo.webp', $this->extension($back, [7 => 'medias/cards/backs/papa-calin-axo.webp'])->standard('axo')['image']);
    }

    /** @param array<int, string> $images */
    private function extension(?CardBack $back, array $images): BackExtension
    {
        $backs = $this->createStub(CardBackRepository::class);
        $backs->method('findStandard')->willReturn($back);
        $pictures = $this->createStub(CardBackPictureRepository::class);
        $pictures->method('findImagesOfPack')->willReturn($images);

        return new BackExtension($backs, $pictures);
    }
}
