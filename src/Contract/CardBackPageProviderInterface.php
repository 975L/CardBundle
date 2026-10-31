<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Contract;

use c975L\CardBundle\Entity\CardBack;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// The page of the site showing a back alone, as its cards wear it (the Back component and the site's own content over it): what the thumbnails are shot from (see BackThumbnailMaker). Optional and tagged, as CardPackProviderInterface: without one the thumbnails are uploaded by hand
#[AutoconfigureTag('c975l.card_back_page_provider')]
interface CardBackPageProviderInterface
{
    // The page's absolute url, null for a back the site cannot show alone
    public function getBackPageUrl(CardBack $back): ?string;
}
