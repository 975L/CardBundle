<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Contracts\Translation\TranslatableInterface;

// What the bundle's back-office screens share: content like any other, behind the editor role, each naming its own labels
trait EditorCrudTrait
{
    // The role the site gives its content editors
    private function roleNeeded(): string
    {
        return (string) $this->configService->get('site-role-editor');
    }

    // Its labels spelled out by the caller, so that the translation keys stay greppable
    /** @param array<string, 'ASC'|'DESC'> $sort */
    private function editorCrud(Crud $crud, TranslatableInterface $singular, TranslatableInterface $plural, TranslatableInterface $help, array $sort): Crud
    {
        return $crud
            ->setEntityLabelInSingular($singular)
            ->setEntityLabelInPlural($plural)
            ->setEntityPermission($this->roleNeeded())
            ->setDefaultSort($sort)
            ->setHelp(Crud::PAGE_INDEX, $help)
            ->showEntityActionsInlined()
        ;
    }

    // Every action behind that role, as icons
    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        foreach ([Action::INDEX, Action::NEW, Action::EDIT, Action::DELETE] as $action) {
            $actions->setPermission($action, $this->roleNeeded());
        }

        return EasyAdminActionHelper::iconOnlyDefaults($actions, $this->translator);
    }
}
