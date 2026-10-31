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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validation;

class CardBackTest extends TestCase
{
    // On sale once published and priced, its store product id derived from its slug - and none without a price
    public function testItsProductIdIsDerivedFromItsSlugOncePriced(): void
    {
        $back = new CardBack()->setSlug('orage')->setPublished(true);
        $this->assertNull($back->getProductId());
        $this->assertFalse($back->isForSale());

        $back->setPrice(100);
        $this->assertSame('back.orage', $back->getProductId());
        $this->assertTrue($back->isForSale());
        $this->assertFalse($back->setPublished(false)->isForSale());
    }

    // The standard back is never sold, a price typed on it notwithstanding
    public function testTheStandardBackIsNeverForSale(): void
    {
        $back = new CardBack()->setSlug('origin')->setPrice(100)->setPublished(true);
        $this->assertTrue($back->isForSale());

        $this->assertFalse($back->setStandard(true)->isForSale());
    }

    // Lowercase words joined by hyphens only: the store id is read back from it (see CardBackRepository::findByProductId()), and stores refuse capitals and accents
    public function testTheSlugIsLowercaseWordsJoinedByHyphens(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $this->assertCount(0, $validator->validateProperty(new CardBack()->setSlug('chateau-hurlton-2'), 'slug'));
        foreach (['night_sky', 'Orage', 'château', 'a--b', '-a'] as $slug) {
            $this->assertCount(1, $validator->validateProperty(new CardBack()->setSlug($slug), 'slug'), $slug);
        }
    }

    public function testItIsNamedByItsName(): void
    {
        $this->assertSame('À l\'aube', (string) new CardBack()->setName('À l\'aube'));
        $this->assertSame('', (string) new CardBack());
    }

    // Its thumbnail is named after its slug, beside the packs' pictures
    public function testTheThumbnailIsNamedAfterItsSlug(): void
    {
        $this->assertSame(CardBack::MEDIA_DIRECTORY . '/aube', new CardBack()->setSlug('aube')->getVichMediaPath());
    }

    // A new thumbnail dates the row, Doctrine seeing no other change when only the file is replaced; clearing the field does not
    public function testANewThumbnailDatesTheRow(): void
    {
        $back = new CardBack();
        $before = $back->getModification();

        $back->setImageFile(null);
        $this->assertSame($before, $back->getModification());

        $back->setImageFile(new File(__FILE__));
        $this->assertNotSame($before, $back->getModification());
    }
}
