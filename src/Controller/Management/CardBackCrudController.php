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
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\VichImageOptions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// The variants of the packs' card backs. A pack's own back is no row here, being whatever the site draws for it; how a back is drawn in the app (its tone and effect over the picture) is the site's stylesheet, so the listing shows the uploaded picture only - a site wanting its own rendering overrides the preview's template
/** @extends AbstractCrudController<CardBack> */
class CardBackCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly CardCatalog $cardCatalog,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CardBack::class;
    }

    // A back is content like any other
    private function roleNeeded(): string
    {
        return (string) $this->configService->get('site-role-editor');
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.card_back', [], 'card'))
            ->setEntityLabelInPlural(t('label.card_backs', [], 'card'))
            ->setEntityPermission($this->roleNeeded())
            ->setDefaultSort(['pack' => 'ASC', 'position' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, t('label.info_card_backs', [], 'card'))
            ->showEntityActionsInlined()
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        foreach ([Action::INDEX, Action::NEW, Action::EDIT, Action::DELETE] as $action) {
            $actions->setPermission($action, $this->roleNeeded());
        }

        return EasyAdminActionHelper::iconOnlyDefaults($actions, $this->translator);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        // Read off the slug, never empty: EasyAdmin draws an empty value as "Empty" without calling the field's template. A site drawing its backs as its app does overrides that template (templates/bundles/c975LCardBundle/management/field_card_back.html.twig)
        yield Field::new('slug', t('label.back_image', [], 'card'))
            ->setTemplatePath('@c975LCard/management/field_card_back.html.twig')
            ->onlyOnIndex();

        yield $this->packField($pageName);

        yield TextField::new('name', t('label.back_name', [], 'card'))
            ->setHelp(t('help.back_name', [], 'card'));

        yield SlugField::new('slug', t('label.back_slug', [], 'card'))
            ->setTargetFieldName('name')
            ->setHelp(t('help.back_slug', [], 'card'))
            ->onlyOnForms();

        yield Field::new('imageFile', t('label.back_image', [], 'card'))
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions(VichImageOptions::default('10M'))
            ->setHelp(t('help.back_image', [], 'card'))
            ->onlyOnForms();

        // No choices given: EasyAdmin reads the enum off the mapping and the enum translates its own label
        yield ChoiceField::new('tone', t('label.back_tone', [], 'card'))
            ->setHelp(t('help.back_tone', [], 'card'));

        yield ChoiceField::new('effect', t('label.back_effect', [], 'card'))
            ->setHelp(t('help.back_effect', [], 'card'));

        yield TextField::new('productId', t('label.back_product_id', [], 'card'))
            ->setHelp(t('help.back_product_id', [], 'card'))
            ->hideOnIndex();

        yield IntegerField::new('position', t('label.back_position', [], 'card'))
            ->setHelp(t('help.back_position', [], 'card'));

        yield BooleanField::new('published', t('label.back_published', [], 'card'))
            ->setHelp(t('help.back_published', [], 'card'));
    }

    // A list of the site's packs when it gives one (see CardPackProviderInterface), the key typed by hand otherwise. Locked once created, a back never moving to another pack: a disabled field is not submitted either, so a back whose pack left the site's list still saves
    private function packField(string $pageName): ChoiceField | TextField
    {
        $isLocked = Crud::PAGE_EDIT === $pageName;
        $packs = $this->cardCatalog->packs();
        if ([] === $packs) {
            return TextField::new('pack', t('label.back_pack', [], 'card'))
                ->setDisabled($isLocked)
                ->setHelp(t($isLocked ? 'help.back_pack_locked' : 'help.back_pack_typed', [], 'card'));
        }

        // Its key beside its name, two packs of the same name staying apart
        $choices = [];
        foreach ($packs as $key => $name) {
            $choices[$name . ' (' . $key . ')'] = $key;
        }

        return ChoiceField::new('pack', t('label.back_pack', [], 'card'))
            ->setChoices($choices)
            ->setDisabled($isLocked)
            ->setHelp(t($isLocked ? 'help.back_pack_locked' : 'help.back_pack', [], 'card'));
    }
}
