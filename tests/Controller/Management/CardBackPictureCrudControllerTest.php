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
use c975L\CardBundle\Controller\Management\CardBackPictureCrudController;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class CardBackPictureCrudControllerTest extends TestCase
{
    use CrudFieldsTrait;

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

    // A picture never moves to another back or pack, its file being named after both: chosen on creation, shown but locked on edition, whichever pack field the site gets
    public function testTheBackAndThePackAreLockedOnceCreated(): void
    {
        foreach ([[], ['hur' => 'Hurlton']] as $packs) {
            foreach (['back', 'pack'] as $property) {
                $this->assertFalse($this->fields($packs, Crud::PAGE_NEW)[$property]->getAsDto()->getFormTypeOption('disabled'));
                $this->assertTrue($this->fields($packs, Crud::PAGE_EDIT)[$property]->getAsDto()->getFormTypeOption('disabled'));
            }
        }
    }

    // The list shows the raw key, a pack that left the site's list staying readable there
    public function testTheListShowsThePacksKey(): void
    {
        $this->assertInstanceOf(TextField::class, $this->fields(['hur' => 'Hurlton'], Crud::PAGE_INDEX)['pack']);
    }

    // A file is required on creation, a row without one holding its back and pack for nothing; kept on edition without a new one
    public function testTheFileIsRequiredOnCreationOnly(): void
    {
        $this->assertTrue($this->fields([], Crud::PAGE_NEW)['imageFile']->getAsDto()->getFormTypeOption('required'));
        $this->assertFalse($this->fields([], Crud::PAGE_EDIT)['imageFile']->getAsDto()->getFormTypeOption('required'));
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
        $controller = new CardBackPictureCrudController($this->createStub(ConfigServiceInterface::class), $this->createStub(TranslatorInterface::class), new CardCatalog([], [$provider], $this->createStub(ConfigServiceInterface::class)));

        return $this->fieldsOf($controller, $pageName);
    }
}
