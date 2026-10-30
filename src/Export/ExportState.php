<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Export;

// What the last runs produced, by item: the fingerprint of what was shot and the files it left. It is what makes the export idempotent - an item whose pages, stylesheets and images are what they were, its files still there, is not shot again - and what a full run prunes, the files of an item the site no longer exports going with it
final class ExportState
{
    /** @param array<string, array{fingerprint: string, outputs: list<string>, manifest?: string, entry?: array<string, mixed>}> $items */
    public function __construct(
        private array $items = [],
    ) {
    }

    // The state as a run left it, empty when no run has or the file does not read
    public static function fromFile(string $file): self
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return new self(\is_array($data) && \is_array($data['items'] ?? null) ? $data['items'] : []);
    }

    // Written whole, item after item, so a run stopped halfway keeps what it made
    public function save(string $file): void
    {
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o775, true);
        }
        ksort($this->items);
        file_put_contents($file, json_encode(['items' => $this->items], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n");
    }

    // What an item is made of: its pages - the stylesheets being written into them - and every file they read from disk, by date and size, an illustration re-uploaded under the same name counting as a change. The salt is the export's own way of rendering, so a change there redoes everything
    public static function fingerprint(string $salt, string ...$pages): string
    {
        $files = [];
        foreach ($pages as $page) {
            preg_match_all('#file://([^"\'()\s?\#]+)#', $page, $matches);
            foreach ($matches[1] as $path) {
                $path = rawurldecode($path);
                $files[$path] = is_file($path) ? filemtime($path) . ':' . filesize($path) : 'missing';
            }
        }
        ksort($files);

        return hash('sha256', $salt . "\0" . implode("\0", $pages) . "\0" . json_encode($files, \JSON_THROW_ON_ERROR));
    }

    // Whether an item can be left as it is: same fingerprint, every file it left still there
    /** @param \Closure(string): bool $exists */
    public function isFresh(string $key, string $fingerprint, \Closure $exists): bool
    {
        $item = $this->items[$key] ?? null;
        if (null === $item || $fingerprint !== $item['fingerprint']) {
            return false;
        }
        foreach ($item['outputs'] as $output) {
            if (!$exists($output)) {
                return false;
            }
        }

        return true;
    }

    // An item as it was just made, its manifest entry kept for the next runs that leave it as it is
    /**
     * @param list<string>         $outputs
     * @param array<string, mixed> $entry
     */
    public function record(string $key, string $fingerprint, array $outputs, ?string $manifest = null, array $entry = []): void
    {
        $this->items[$key] = ['fingerprint' => $fingerprint, 'outputs' => $outputs] + (null === $manifest ? [] : ['manifest' => $manifest, 'entry' => $entry]);
    }

    // The items no longer exported forgotten, their files returned for removal - files another item still leaves being kept
    /**
     * @param list<string> $keys the items the run exported
     *
     * @return list<string>
     */
    public function prune(array $keys): array
    {
        $gone = array_diff_key($this->items, array_flip($keys));
        $this->items = array_intersect_key($this->items, array_flip($keys));

        $kept = array_merge([], ...array_values(array_map(static fn (array $item): array => $item['outputs'], $this->items)));
        $removed = array_merge([], ...array_values(array_map(static fn (array $item): array => $item['outputs'], $gone)));

        return array_values(array_diff($removed, $kept));
    }

    // The manifests the items name
    /** @return list<string> */
    public function manifests(): array
    {
        return array_values(array_unique(array_filter(array_map(static fn (array $item): ?string => $item['manifest'] ?? null, $this->items))));
    }

    // One manifest's entries, rebuilt from the items and not merged into the file: a card no longer exported leaves it with its files
    /** @return list<array<string, mixed>> */
    public function manifest(string $manifest): array
    {
        $entries = [];
        foreach ($this->items as $key => $item) {
            if ($manifest === ($item['manifest'] ?? null)) {
                $entries[$key] = $item['entry'] ?? [];
            }
        }
        ksort($entries);

        return array_values($entries);
    }

    // Whether the state knows the item
    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }
}
