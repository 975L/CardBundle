<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use c975L\CardBundle\Entity\CardBackPicture;
use c975L\CardBundle\Form\BackImageOptions;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Each back's picture for one pack, a back with none for a pack drawing that pack's own picture in its tone
/** @extends AbstractCrudController<CardBackPicture> */
class CardBackPictureCrudController extends AbstractCrudController
{
    use EditorCrudTrait;
    use PackFieldTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly CardCatalog $cardCatalog,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CardBackPicture::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $this->editorCrud($crud, t('label.card_back_picture', [], 'card'), t('label.card_back_pictures', [], 'card'), t('label.info_card_back_pictures', [], 'card'), ['back' => 'ASC', 'pack' => 'ASC']);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        // The thumbnail UiBundle cuts beside every upload (see CardBackPicture::getThumbnailSize)
        yield Field::new('image', t('label.back_image', [], 'card'))
            ->setTemplatePath('@c975LCard/management/field_card_back_picture.html.twig')
            ->onlyOnIndex();

        // Locked once created, as its pack: the file is named after both
        $isLocked = Crud::PAGE_EDIT === $pageName;
        yield AssociationField::new('back', t('label.card_back', [], 'card'))
            ->setDisabled($isLocked)
            ->setHelp(t('help.back_picture_back', [], 'card'));

        yield $this->packField($pageName, $isLocked, t('help.back_pack', [], 'card'), t('help.back_pack_typed', [], 'card'));

        // Required on creation only, a row without a file holding its back and pack for nothing
        yield Field::new('imageFile', t('label.back_image', [], 'card'))
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions(BackImageOptions::default())
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->setHelp(t('help.back_image', [], 'card'))
            ->onlyOnForms();
    }
}
