<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CollectedBack;
use c975L\CardBundle\Form\BackImageOptions;
use c975L\CardBundle\Service\BackStyles;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// The card backs: the standard one every card wears until another is chosen, and the variants, each held once for every pack; their pictures, pack by pack, are CardBackPictureCrudController's, a back with none for a pack drawn on the pack's own picture; the listing shows no preview of how a back is drawn (the Back component and the site's own content over it) unless the site overrides its template
/** @extends AbstractCrudController<CardBack> */
class CardBackCrudController extends AbstractCrudController
{
    use EditorCrudTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly CardCatalog $cardCatalog,
        private readonly BackStyles $backStyles,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CardBack::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $this->editorCrud($crud, t('label.card_back', [], 'card'), t('label.card_backs', [], 'card'), t('label.info_card_backs', [], 'card'), ['position' => 'ASC']);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        // Read off the slug, never empty: EasyAdmin draws an empty value as "Empty" without calling the field's template. A site drawing its backs as its app does overrides that template (templates/bundles/c975LCardBundle/management/field_card_back.html.twig), which shows nothing otherwise
        yield Field::new('slug', t('label.back_image', [], 'card'))
            ->setTemplatePath('@c975LCard/management/field_card_back.html.twig')
            ->onlyOnIndex();

        yield TextField::new('name', t('label.back_name', [], 'card'))
            ->setHelp(t('help.back_name', [], 'card'));

        // Locked once created: rewards, codes and the stores' product id all name the back by it
        $isEdit = Crud::PAGE_EDIT === $pageName;
        yield SlugField::new('slug', t('label.back_slug', [], 'card'))
            ->setTargetFieldName('name')
            ->setDisabled($isEdit)
            ->setHelp(t($isEdit ? 'help.back_slug_locked' : 'help.back_slug', [], 'card'))
            ->onlyOnForms();

        // What the site offers and draws, "none" alone on a site offering nothing
        yield ChoiceField::new('tone', t('label.back_tone', [], 'card'))
            ->setTranslatableChoices($this->backStyles->tones())
            ->setHelp(t('help.back_tone', [], 'card'));

        yield ChoiceField::new('effect', t('label.back_effect', [], 'card'))
            ->setTranslatableChoices($this->backStyles->effects())
            ->setHelp(t('help.back_effect', [], 'card'));

        // Its image on its own, where no pack draws it: the basket, the order emails, the invoice
        yield Field::new('imageFile', t('label.back_thumbnail', [], 'card'))
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions(BackImageOptions::default())
            ->setHelp(t('help.back_thumbnail', [], 'card'))
            ->onlyOnForms();

        yield MoneyField::new('price', t('label.back_price', [], 'card'))
            ->setCurrency($this->cardCatalog->currency())
            ->setStoredAsCents()
            ->setHelp(t('help.back_price', [], 'card'));

        yield NumberField::new('vat', t('label.back_vat', [], 'card'))
            ->setNumDecimals(1)
            ->setHelp(t('help.back_vat', [], 'card'))
            ->hideOnIndex();

        yield IntegerField::new('position', t('label.back_position', [], 'card'))
            ->setHelp(t('help.back_position', [], 'card'));

        yield BooleanField::new('published', t('label.back_published', [], 'card'))
            ->setHelp(t('help.back_published', [], 'card'));

        yield BooleanField::new('standard', t('label.back_standard', [], 'card'))
            ->setHelp(t('help.back_standard', [], 'card'));
    }

    // One standard back per site: the one saved as such unmarks the others
    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->keepOneStandard($entityManager, $entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->keepOneStandard($entityManager, $entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    // The other standard backs unmarked, flushed with the one saved
    private function keepOneStandard(EntityManagerInterface $entityManager, mixed $back): void
    {
        if (!$back instanceof CardBack || !$back->isStandard()) {
            return;
        }

        foreach ($entityManager->getRepository(CardBack::class)->findBy(['standard' => true]) as $other) {
            if ($other !== $back) {
                $other->setStandard(false);
            }
        }
    }

    // The editor's actions, without the batch delete: one held among the selection would be refused, the others gone
    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $this->editorActions($actions)->disable(Action::BATCH_DELETE);
    }

    // A back someone holds is refused, unpublishing it being what keeps the purchases
    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->deleteUnlessHeld($entityManager, $entityInstance, CollectedBack::class, 'back', 'label.back_held_not_deleted');
    }
}
