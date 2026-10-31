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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
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
