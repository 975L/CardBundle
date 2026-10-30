<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Controller\Management;

use c975L\CardBundle\Contract\CardPackProviderInterface;
use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class CardBackCrudControllerTest extends TestCase
{
    // The preview names its template as a string, which nothing compiles: a renamed file breaks the listing, and that path is also what a site overrides
    public function testThePreviewTemplateExists(): void
    {
        $root = \dirname(__DIR__, 3);
        $this->assertSame(1, preg_match("#'@c975LCard/([^']+)'#", (string) file_get_contents($root . '/src/Controller/Management/CardBackCrudController.php'), $matches));
        $this->assertFileExists($root . '/templates/' . $matches[1]);
    }

    // A site naming no pack types the key by hand, rather than facing an empty list it could never fill
    public function testThePackIsTypedWhenTheSiteListsNone(): void
    {
        $pack = $this->fields([], Crud::PAGE_NEW)['pack'];

        $this->assertInstanceOf(TextField::class, $pack);
        $this->assertEquals(t('help.back_pack_typed', [], 'card'), $pack->getAsDto()->getHelp());
    }

    // A site listing its packs offers them by name and key, the key being what is stored and two packs of the same name staying apart
    public function testThePackIsChosenAmongTheSitesPacks(): void
    {
        $pack = $this->fields(['hur' => 'Hurlton', 'gob' => 'Hurlton'], Crud::PAGE_NEW)['pack'];

        $this->assertInstanceOf(ChoiceField::class, $pack);
        $this->assertSame(['Hurlton (hur)' => 'hur', 'Hurlton (gob)' => 'gob'], $pack->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES));
    }

    // A back never moves to another pack: chosen on creation, shown but locked on edition, whichever field the site gets
    public function testThePackIsLockedOnceCreated(): void
    {
        foreach ([[], ['hur' => 'Hurlton']] as $packs) {
            $this->assertFalse($this->fields($packs, Crud::PAGE_NEW)['pack']->getAsDto()->getFormTypeOption('disabled'));

            $pack = $this->fields($packs, Crud::PAGE_EDIT)['pack']->getAsDto();
            $this->assertTrue($pack->getFormTypeOption('disabled'));
            $this->assertEquals(t('help.back_pack_locked', [], 'card'), $pack->getHelp());
        }
    }

    // No choices are given for the tone and the effect: EasyAdmin then reads the enum off the mapping and stores a case, never a string the setter would refuse
    public function testToneAndEffectAreLeftToTheirEnum(): void
    {
        $fields = $this->fields([], Crud::PAGE_NEW);

        foreach (['tone', 'effect'] as $property) {
            $this->assertNull($fields[$property]->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES));
        }
    }

    // The enum translates itself, which is how the form and the listing show its label
    public function testToneAndEffectTranslateTheirOwnLabel(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->exactly(2))->method('trans')->willReturnCallback(static fn (string $key, array $parameters, string $domain, ?string $locale): string => $domain . ':' . $key . ':' . $locale);

        $this->assertSame('card:label.back_tone_dawn:fr', BackTone::Dawn->trans($translator, 'fr'));
        $this->assertSame('card:label.back_effect_snow:es', BackEffect::Snow->trans($translator, 'es'));
    }

    // The fields a page shows, by property
    /**
     * @param array<string, string> $packs
     *
     * @return array<string, FieldInterface>
     */
    private function fields(array $packs, string $pageName): array
    {
        $provider = $this->createStub(CardPackProviderInterface::class);
        $provider->method('getPacks')->willReturn($packs);
        $controller = new CardBackCrudController($this->createStub(ConfigServiceInterface::class), $this->createStub(TranslatorInterface::class), new CardCatalog([], [$provider]));

        $fields = [];
        foreach ($controller->configureFields($pageName) as $field) {
            if ($field->getAsDto()->isDisplayedOn($pageName)) {
                $fields[$field->getAsDto()->getProperty()] = $field;
            }
        }

        return $fields;
    }
}
