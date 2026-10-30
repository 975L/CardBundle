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
use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class CardBackTest extends TestCase
{
    // A tone and an effect drawn on the same layer are refused, on the effect; on different layers they pass
    public function testATonAndAnEffectOnTheSameLayerAreRefused(): void
    {
        $this->assertSame(1, $this->violations(BackTone::Dawn, BackEffect::Snow));
        $this->assertSame(0, $this->violations(BackTone::Dawn, BackEffect::Stars));
        $this->assertSame(0, $this->violations(BackTone::None, BackEffect::Mist));
    }

    // Each upload lands under the bundle's own directory, named after the slug
    public function testTheMediaPathIsNamedAfterTheSlug(): void
    {
        $this->assertSame(CardBack::MEDIA_DIRECTORY . '/hurlton-aube', new CardBack()->setSlug('hurlton-aube')->getVichMediaPath());
    }

    public function testItIsNamedByItsPackAndItsName(): void
    {
        $this->assertSame('hur - À l\'aube', (string) new CardBack()->setPack('hur')->setName('À l\'aube'));
        $this->assertSame('', (string) new CardBack());
    }

    // A new upload dates the row, Doctrine seeing no other change when only the file is replaced; clearing the field does not
    public function testANewPictureDatesTheRow(): void
    {
        $back = new CardBack();
        $this->assertSame($back->getCreation(), $back->getModification());

        $back->setImageFile(null);
        $this->assertSame($back->getCreation(), $back->getModification());

        $back->setImageFile(new File(__FILE__));
        $this->assertNotSame($back->getCreation(), $back->getModification());
    }

    private function violations(BackTone $tone, BackEffect $effect): int
    {
        $count = 0;
        $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
        $builder->method('setTranslationDomain')->willReturnSelf();
        $builder->method('atPath')->willReturnSelf();
        $builder->method('addViolation')->willReturnCallback(static function () use (&$count): void {
            ++$count;
        });
        $context = $this->createStub(ExecutionContextInterface::class);
        $context->method('buildViolation')->willReturn($builder);

        new CardBack()->setTone($tone)->setEffect($effect)->validateLayers($context);

        return $count;
    }
}
