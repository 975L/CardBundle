<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Form\BackImageOptions;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// The skins of the card faces, each held pack by pack; their pictures, card by card, are CardSkinPictureCrudController's, a card with none keeping its own
/** @extends AbstractCrudController<CardSkin> */
class CardSkinCrudController extends AbstractCrudController
{
    use EditorCrudTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly CardCatalog $cardCatalog,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CardSkin::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $this->editorCrud($crud, t('label.card_skin', [], 'card'), t('label.card_skins', [], 'card'), t('label.info_card_skins', [], 'card'), ['position' => 'ASC']);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        // The thumbnail UiBundle cuts beside its image, as for a back's picture
        yield Field::new('image', t('label.back_thumbnail', [], 'card'))
            ->setTemplatePath('@c975LCard/management/field_card_back_picture.html.twig')
            ->onlyOnIndex();

        yield TextField::new('name', t('label.back_name', [], 'card'))
            ->setHelp(t('help.skin_name', [], 'card'));

        // Locked once created: rewards, codes and the stores' product ids all name the skin by it
        $isEdit = Crud::PAGE_EDIT === $pageName;
        yield SlugField::new('slug', t('label.back_slug', [], 'card'))
            ->setTargetFieldName('name')
            ->setDisabled($isEdit)
            ->setHelp(t($isEdit ? 'help.skin_slug_locked' : 'help.skin_slug', [], 'card'))
            ->onlyOnForms();

        // Its image on its own, where no card draws it: the basket, the order emails, the invoice
        yield Field::new('imageFile', t('label.back_thumbnail', [], 'card'))
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions(BackImageOptions::default())
            ->setHelp(t('help.skin_thumbnail', [], 'card'))
            ->onlyOnForms();

        yield MoneyField::new('price', t('label.back_price', [], 'card'))
            ->setCurrency($this->cardCatalog->currency())
            ->setStoredAsCents()
            ->setHelp(t('help.skin_price', [], 'card'));

        yield NumberField::new('vat', t('label.back_vat', [], 'card'))
            ->setNumDecimals(1)
            ->setHelp(t('help.back_vat', [], 'card'))
            ->hideOnIndex();

        yield IntegerField::new('position', t('label.back_position', [], 'card'))
            ->setHelp(t('help.skin_position', [], 'card'));

        yield BooleanField::new('published', t('label.back_published', [], 'card'))
            ->setHelp(t('help.skin_published', [], 'card'));
    }
}
