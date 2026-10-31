<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Management;

use c975L\CardBundle\Controller\Management\BackThumbnailsController;
use c975L\CardBundle\Service\BackThumbnailMaker;
use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The backs' thumbnails as a dashboard tile, shown only where they can be made: Chrome is installed locally and not on the managed production server
class BackThumbnailsShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly BackThumbnailMaker $backThumbnailMaker,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getShortcuts(): array
    {
        if (!$this->backThumbnailMaker->isAvailable()) {
            return [];
        }

        return [[
            'label' => $this->translator->trans('label.back_thumbnails_make', [], 'card'),
            'icon' => 'fas fa-camera',
            'route' => BackThumbnailsController::ROUTE,
            'active' => false,
            'role' => BackThumbnailsController::ROLE,
            'category' => self::CATEGORY_EXPORT,
            'method' => 'POST',
        ]];
    }
}
