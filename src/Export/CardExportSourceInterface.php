<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Export;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// What the site exports of its cards, paper and social networks alike: the pages come already rendered by the site's own Twig under its own stylesheets (see PageRenderer), the bundle only shooting them and keeping what has not changed. Tagged rather than aliased, so a site implementing it has nothing to configure - without one the export tile and the command have nothing to do
#[AutoconfigureTag('c975l.card_export_source')]
interface CardExportSourceInterface
{
    // Every page to produce, or only those the target names - its reading is the site's own ("heros/12", "characters"), null for everything
    /**
     * @return iterable<PdfItem|SocialItem>
     *
     * @throws \InvalidArgumentException when the target names nothing the site knows
     */
    public function getExportItems(?string $target): iterable;
}
