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
use c975L\CardBundle\Controller\Management\CardBackPictureCrudController;
use c975L\CardBundle\Controller\Management\CardSkinCrudController;
use c975L\CardBundle\Controller\Management\CardSkinPictureCrudController;
use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

// Four entries, the card backs and their pictures pack by pack, the skins and their pictures card by card: the cards themselves are the site's catalogue and have their own screens there, and the collections are what players did rather than what an admin makes
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
            'card_back_picture' => [
                'controller' => CardBackPictureCrudController::class,
                'label' => 'label.card_back_pictures',
                'narration' => 'narration.card_back_pictures',
                'translation_domain' => 'card',
                'icon' => 'fas fa-image',
                'description' => 'label.info_card_back_pictures',
                'role' => (string) $this->configService->get('site-role-editor'),
            ],
            'card_skin' => [
                'controller' => CardSkinCrudController::class,
                'label' => 'label.card_skins',
                'narration' => 'narration.card_skins',
                'translation_domain' => 'card',
                'icon' => 'fas fa-palette',
                'description' => 'label.info_card_skins',
                'role' => (string) $this->configService->get('site-role-editor'),
            ],
            'card_skin_picture' => [
                'controller' => CardSkinPictureCrudController::class,
                'label' => 'label.card_skin_pictures',
                'narration' => 'narration.card_skin_pictures',
                'translation_domain' => 'card',
                'icon' => 'fas fa-images',
                'description' => 'label.info_card_skin_pictures',
                'role' => (string) $this->configService->get('site-role-editor'),
            ],
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
