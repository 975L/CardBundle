<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Message;

// The export ordered from the dashboard tile, run by the worker (see ExportCardsHandler) - a whole run, the tile having no target to give
final readonly class ExportCardsMessage
{
    public function __construct(
        public bool $force = false,
    ) {
    }
}
