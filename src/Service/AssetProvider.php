<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\UiBundle\Contract\BundleScriptProviderInterface;
use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;

// The backs drawn on public pages (see templates/components/Back.html.twig): their controller and their stylesheet, loaded site-wide by UiBundle's layout, the controller itself registered only on a page showing a back
class AssetProvider implements BundleScriptProviderInterface, BundleStylesheetProviderInterface
{
    public function getScripts(): array
    {
        return [
            '@c975l/card-bundle/controllers.js',
        ];
    }

    public function getStylesheets(): array
    {
        return [
            'bundles/c975lcard/css/styles.min.css',
        ];
    }
}
