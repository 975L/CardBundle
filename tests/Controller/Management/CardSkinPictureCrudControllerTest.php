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
use c975L\CardBundle\Controller\Management\CardSkinPictureCrudController;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class CardSkinPictureCrudControllerTest extends TestCase
{
    use CrudFieldsTrait;

    // The pack is offered among the site's, typed by hand when it lists none, each with the skins' own help
    public function testThePackFieldFollowsTheSitesPacks(): void
    {
        $typed = $this->fields([], Crud::PAGE_NEW)['pack'];
        $chosen = $this->fields(['GOB' => 'Gobelins'], Crud::PAGE_NEW)['pack'];

        $this->assertInstanceOf(TextField::class, $typed);
        $this->assertEquals(t('help.skin_pack_typed', [], 'card'), $typed->getAsDto()->getHelp());
        $this->assertInstanceOf(ChoiceField::class, $chosen);
        $this->assertSame(['Gobelins (GOB)' => 'GOB'], $chosen->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES));
    }

    // A picture never moves to another skin, pack or card, its file being named after them: chosen on creation, locked on edition
    public function testTheSkinThePackAndTheCardAreLockedOnceCreated(): void
    {
        foreach (['skin', 'pack', 'reference'] as $property) {
            $this->assertFalse($this->fields([], Crud::PAGE_NEW)[$property]->getAsDto()->getFormTypeOption('disabled'));
            $this->assertTrue($this->fields([], Crud::PAGE_EDIT)[$property]->getAsDto()->getFormTypeOption('disabled'));
        }
    }

    // A file is required on creation only
    public function testTheFileIsRequiredOnCreationOnly(): void
    {
        $this->assertTrue($this->fields([], Crud::PAGE_NEW)['imageFile']->getAsDto()->getFormTypeOption('required'));
        $this->assertFalse($this->fields([], Crud::PAGE_EDIT)['imageFile']->getAsDto()->getFormTypeOption('required'));
    }

    /**
     * @param array<string, string> $packs
     *
     * @return array<string, FieldInterface>
     */
    private function fields(array $packs, string $pageName): array
    {
        $provider = $this->createStub(CardPackProviderInterface::class);
        $provider->method('getPacks')->willReturn($packs);
        $controller = new CardSkinPictureCrudController($this->createStub(ConfigServiceInterface::class), $this->createStub(TranslatorInterface::class), new CardCatalog([], [$provider], $this->createStub(ConfigServiceInterface::class)));

        return $this->fieldsOf($controller, $pageName);
    }
}
