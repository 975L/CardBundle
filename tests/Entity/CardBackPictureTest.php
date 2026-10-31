<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Entity;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CardBackPicture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validation;

class CardBackPictureTest extends TestCase
{
    // Each upload lands under the bundle's own directory, named after its back and its pack
    public function testTheMediaPathIsNamedAfterTheBackAndThePack(): void
    {
        $picture = new CardBackPicture()->setBack(new CardBack()->setSlug('aube'))->setPack('HUR');

        $this->assertSame(CardBackPicture::MEDIA_DIRECTORY . '/aube-hur', $picture->getVichMediaPath());
    }

    // Its slug is written from its back and its pack as soon as both are set, never typed, so that UniqueEntity sees it before saving: what a content sync matches it by
    public function testItsSlugIsItsBacksAndItsPack(): void
    {
        $picture = new CardBackPicture()->setBack(new CardBack()->setSlug('aube'));
        $this->assertNull($picture->getSlug());

        $picture->setPack('HUR');
        $this->assertSame('aube-hur', $picture->getSlug());

        $unfinished = new CardBackPicture()->setSlug('kept');
        $unfinished->setSlugFromBackAndPack();
        $this->assertSame('kept', $unfinished->getSlug());
    }

    // Letters, digits, dashes and underscores only: the pack goes into the slug and the file's path
    public function testThePackIsAKeyWithoutSpacesOrSlashes(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $this->assertCount(0, $validator->validateProperty(new CardBackPicture()->setPack('HUR_2-b'), 'pack'));
        foreach (['Château Nord', 'a/b', 'château'] as $pack) {
            $this->assertCount(1, $validator->validateProperty(new CardBackPicture()->setPack($pack), 'pack'), $pack);
        }
    }

    public function testItIsNamedByItsBackAndItsPack(): void
    {
        $this->assertSame('À l\'aube - hur', (string) new CardBackPicture()->setBack(new CardBack()->setName('À l\'aube'))->setPack('hur'));
        $this->assertSame('', (string) new CardBackPicture());
    }

    // A new upload dates the row, Doctrine seeing no other change when only the file is replaced; clearing the field does not
    public function testANewPictureDatesTheRow(): void
    {
        $picture = new CardBackPicture();
        $before = $picture->getModification();

        $picture->setImageFile(null);
        $this->assertSame($before, $picture->getModification());

        $picture->setImageFile(new File(__FILE__));
        $this->assertNotSame($before, $picture->getModification());
    }
}
