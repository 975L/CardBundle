<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Export;

// One card in one finish for the social networks: the still image a post shows (1080x1350) and the video Reels and Shorts play (1080x1920), the page exposing still() and frame(t) to pose them. Its entry goes into the manifest the drafts of the posts are made from
final readonly class SocialItem
{
    /** @param array<string, mixed> $entry what the manifest says of the card, the files and duration added by the export */
    public function __construct(
        public string $path,
        public string $postHtml,
        public string $reelHtml,
        public string $manifest,
        public array $entry = [],
    ) {
    }

    public function jpg(): string
    {
        return $this->path . '.jpg';
    }

    public function mp4(): string
    {
        return $this->path . '.mp4';
    }

    // The files this item leaves behind
    /** @return list<string> */
    public function outputs(): array
    {
        return [$this->jpg(), $this->mp4()];
    }

    // The manifest's entry as the export writes it
    /** @return array<string, mixed> */
    public function manifestEntry(float $duration): array
    {
        return [...$this->entry, 'jpg' => $this->jpg(), 'mp4' => $this->mp4(), 'duration' => $duration];
    }
}
