<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

// The pack field a back's picture and a skin's picture share, the class holding $cardCatalog
trait PackFieldTrait
{
    // A list of the site's packs when it gives one (see CardPackProviderInterface), the key typed by hand otherwise. A disabled field is not submitted either, so a picture whose pack left the site's list still saves, and the list shows the raw key so that such a pack stays readable
    private function packField(string $pageName, bool $isLocked, TranslatableInterface $help, TranslatableInterface $typedHelp): ChoiceField | TextField
    {
        $packs = Crud::PAGE_INDEX === $pageName ? [] : $this->cardCatalog->packs();
        if ([] === $packs) {
            return TextField::new('pack', t('label.back_pack', [], 'card'))
                ->setDisabled($isLocked)
                ->setHelp($typedHelp);
        }

        // Its key beside its name, two packs of the same name staying apart
        $choices = [];
        foreach ($packs as $key => $name) {
            $choices[$name . ' (' . $key . ')'] = $key;
        }

        return ChoiceField::new('pack', t('label.back_pack', [], 'card'))
            ->setChoices($choices)
            ->setDisabled($isLocked)
            ->setHelp($help);
    }
}
