<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Controller\Management;

use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class CardBackCrudControllerTest extends TestCase
{
    use CrudFieldsTrait;

    // The previews name their template as a string, which nothing compiles: a renamed file breaks the listing, and that path is also what a site overrides
    public function testThePreviewTemplateExists(): void
    {
        $root = \dirname(__DIR__, 3);
        foreach (['CardBackCrudController', 'CardBackPictureCrudController'] as $controller) {
            $this->assertSame(1, preg_match("#'@c975LCard/([^']+)'#", (string) file_get_contents($root . '/src/Controller/Management/' . $controller . '.php'), $matches));
            $this->assertFileExists($root . '/templates/' . $matches[1]);
        }
    }

    // The slug is typed on creation and locked afterwards: rewards, codes and the stores name the back by it
    public function testTheSlugIsLockedOnceCreated(): void
    {
        $this->assertFalse($this->fields(Crud::PAGE_NEW)['slug']->getAsDto()->getFormTypeOption('disabled'));

        $slug = $this->fields(Crud::PAGE_EDIT)['slug']->getAsDto();
        $this->assertTrue($slug->getFormTypeOption('disabled'));
        $this->assertEquals(t('help.back_slug_locked', [], 'card'), $slug->getHelp());
    }

    // No choices are given for the tone and the effect: EasyAdmin then reads the enum off the mapping and stores a case, never a string the setter would refuse
    public function testToneAndEffectAreLeftToTheirEnum(): void
    {
        $fields = $this->fields(Crud::PAGE_NEW);

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
     * @return array<string, FieldInterface>
     */
    private function fields(string $pageName): array
    {
        $controller = new CardBackCrudController($this->createStub(ConfigServiceInterface::class), $this->createStub(TranslatorInterface::class), new CardCatalog([], [], $this->createStub(ConfigServiceInterface::class)));

        return $this->fieldsOf($controller, $pageName);
    }
}
