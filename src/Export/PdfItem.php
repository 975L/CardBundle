<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Export;

// One document printed to PDF from a page the site rendered: a booklet, a plate, a series of printer-ready cards. With faces, each page is also drawn as an image at 600 dpi into the folder named after the PDF, a face left null being a page dropped (the same back on every card)
final readonly class PdfItem
{
    /** @param list<string|null> $faces the name of each page, in their order, without extension */
    public function __construct(
        public string $path,
        public string $html,
        public array $faces = [],
    ) {
        if (!str_ends_with($path, '.pdf')) {
            throw new \InvalidArgumentException(\sprintf('A PDF is written to a ".pdf" path, "%s" given.', $path));
        }
    }

    // The folder the faces are drawn into
    public function imageDirectory(): string
    {
        return substr($this->path, 0, -4);
    }

    // The files this item leaves behind
    /** @return list<string> */
    public function outputs(): array
    {
        $outputs = [$this->path];
        foreach ($this->faces as $face) {
            if (null !== $face) {
                $outputs[] = $this->imageDirectory() . '/' . $face . '.jpg';
            }
        }

        return $outputs;
    }
}
