<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Management;

use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

// One entry, the card backs: the cards themselves are the site's catalogue and have their own screens there, and the collections are what players did rather than what an admin makes
class MenuProvider implements MenuProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getMenuSection(): array
    {
        return [
            'label' => 'label.cards',
            'translation_domain' => 'card',
            'icon' => 'fas fa-layer-group',
        ];
    }

    public function getMenus(): array
    {
        return [
            'card_back' => [
                'controller' => CardBackCrudController::class,
                'label' => 'label.card_backs',
                'narration' => 'narration.card_backs',
                'translation_domain' => 'card',
                'icon' => 'fas fa-clone',
                'description' => 'label.info_card_backs',
                // The bar CardBackCrudController sets on its own index - a back is content like any other
                'role' => (string) $this->configService->get('site-role-editor'),
            ],
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
