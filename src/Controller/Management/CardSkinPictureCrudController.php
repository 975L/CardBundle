<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use c975L\CardBundle\Entity\CardSkinPicture;
use c975L\CardBundle\Form\BackImageOptions;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Each skin's picture for one card, filed under the card's pack: the packs a skin dresses, and is sold in, are those its pictures are for
/** @extends AbstractCrudController<CardSkinPicture> */
class CardSkinPictureCrudController extends AbstractCrudController
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
        return CardSkinPicture::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $this->editorCrud($crud, t('label.card_skin_picture', [], 'card'), t('label.card_skin_pictures', [], 'card'), t('label.info_card_skin_pictures', [], 'card'), ['skin' => 'ASC', 'reference' => 'ASC']);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        // The thumbnail UiBundle cuts beside every upload
        yield Field::new('image', t('label.back_image', [], 'card'))
            ->setTemplatePath('@c975LCard/management/field_card_back_picture.html.twig')
            ->onlyOnIndex();

        // Locked once created, as its card: the file is named after both
        $isLocked = Crud::PAGE_EDIT === $pageName;
        yield AssociationField::new('skin', t('label.card_skin', [], 'card'))
            ->setDisabled($isLocked)
            ->setHelp(t('help.skin_picture_skin', [], 'card'));

        yield $this->packField($pageName, $isLocked, t('help.skin_pack', [], 'card'), t('help.skin_pack_typed', [], 'card'));

        yield TextField::new('reference', t('label.skin_reference', [], 'card'))
            ->setDisabled($isLocked)
            ->setHelp(t('help.skin_reference', [], 'card'));

        // Required on creation only, a row without a file holding its skin and card for nothing
        yield Field::new('imageFile', t('label.back_image', [], 'card'))
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions(BackImageOptions::default())
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->setHelp(t('help.skin_image', [], 'card'))
            ->onlyOnForms();
    }
}
