<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Form;

use c975L\UiBundle\Form\VichImageOptions;
use Symfony\Component\Validator\Constraints\File;

// The upload field of a card back's images, its thumbnail as its packs' pictures: UiBundle's options, limited to the images whose stored extension UiMediaNamer decides itself, read off the file's own bytes
final class BackImageOptions
{
    private const string MAX_SIZE = '10M';

    private const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** @return array<string, mixed> */
    public static function default(): array
    {
        $options = VichImageOptions::default(self::MAX_SIZE);
        $options['constraints'][] = new File(mimeTypes: self::MIME_TYPES);

        return $options;
    }
}
