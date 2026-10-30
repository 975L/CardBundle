<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Export;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Filesystem\Filesystem;

// The one export of the cards, paper and social networks alike, from what the site's sources hand over (see CardExportSourceInterface): Chrome prints each document to PDF and Ghostscript draws its faces, Chrome held open shoots the posts and the frames ffmpeg puts together into the videos. Idempotent: an item whose fingerprint has not moved since the last run is left as it is (see ExportState), so a run after a single edit only redoes that edit. The files land where the site says, under the project and outside public/, kept from one run to the next - and pruned of what a full run no longer exports
class CardExporter
{
    // The state of the last runs, beside the files it describes
    public const string STATE_FILE = 'Construction/card-export.json';

    // The still image, 4:5 as Instagram crops the feed, and the video, 9:16 for Reels and Shorts, its length being what frame(t) of the page plays
    public const int POST_WIDTH = 1080;
    public const int POST_HEIGHT = 1350;
    public const int REEL_WIDTH = 1080;
    public const int REEL_HEIGHT = 1920;
    public const int FPS = 30;
    public const float DURATION = 7.0;

    // The export's own way of rendering, part of every fingerprint: raised when a size, a resolution or an encoding setting changes, everything is shot again
    private const string RENDERING = '1';

    // The frames, the pages and Chrome's profile, gone once the run is over
    private const string WORK_DIR = 'card-export';

    private readonly Filesystem $filesystem;

    private ?ChromeSession $chrome = null;

    /** @param iterable<CardExportSourceInterface> $sources */
    public function __construct(
        #[AutowireIterator('c975l.card_export_source')]
        private readonly iterable $sources,
        private readonly PageRenderer $renderer,
        private readonly ExportStatus $status,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        $this->filesystem = new Filesystem();
    }

    // Whether the site exports anything and the machine can render it, which keeps the tile off the production back-office
    public function isAvailable(): bool
    {
        return [] !== iterator_to_array($this->sources, false) && null !== $this->renderer->browser() && null !== $this->renderer->program('gs');
    }

    // What has to be produced, or all of it with $force, the rest left as it is. Each item is told through $notify as it is made
    /**
     * @return array{made: int, kept: int, removed: int}
     *
     * @throws \InvalidArgumentException when the target names nothing
     * @throws \RuntimeException         when a renderer is missing or fails
     */
    public function export(?string $target = null, bool $force = false, ?\Closure $notify = null): array
    {
        // The work folder is emptied below: another run under way, from the worker or the terminal, would lose its frames and Chrome. Only a run counts, an order being what the worker starts on
        if ('running' === ($this->status->current()['state'] ?? null)) {
            throw new \RuntimeException('An export is already running.');
        }

        $items = $this->items($target);
        $this->status->step(ExportStatus::PREPARING);
        $stateFile = $this->absolute(self::STATE_FILE);
        $workDir = \sprintf('%s/var/%s/%s', $this->projectDir, $this->environment, self::WORK_DIR);

        $counts = ['made' => 0, 'kept' => 0, 'removed' => 0];
        try {
            $state = ExportState::fromFile($stateFile);
            $this->filesystem->remove($workDir);
            $this->filesystem->mkdir($workDir);

            foreach ($items as $key => $item) {
                $fingerprint = ExportState::fingerprint(self::RENDERING . "\0" . json_encode(self::meta($item), \JSON_THROW_ON_ERROR), ...self::pages($item));
                if (!$force && $state->isFresh($key, $fingerprint, fn (string $output): bool => is_file($this->absolute($output)))) {
                    ++$counts['kept'];
                    continue;
                }
                $this->notify($notify, $key);
                $this->produce($item, $workDir);
                $item instanceof SocialItem
                    ? $state->record($key, $fingerprint, $item->outputs(), $item->manifest, $item->manifestEntry(self::DURATION))
                    : $state->record($key, $fingerprint, $item->outputs());
                $state->save($stateFile);
                ++$counts['made'];
            }

            // Only a full run knows what the site no longer exports
            if (null === $target) {
                $counts['removed'] = $this->prune($state, array_keys($items));
            }
            $this->writeManifests($state);
            $state->save($stateFile);
        } finally {
            $this->chrome?->close();
            $this->chrome = null;
            $this->filesystem->remove($workDir);
            $this->status->finish();
        }

        return $counts;
    }

    // The items of every source, by the path they write to - two items writing the same file being a mistake of the site's
    /**
     * @return array<string, PdfItem|SocialItem>
     *
     * @throws \InvalidArgumentException when the target names nothing, or two items share a path
     */
    private function items(?string $target): array
    {
        $items = [];
        foreach ($this->sources as $source) {
            foreach ($source->getExportItems($target) as $item) {
                self::assertRelative($item->path);
                if (isset($items[$item->path])) {
                    throw new \InvalidArgumentException(\sprintf('Two exported pages write "%s".', $item->path));
                }
                $items[$item->path] = $item;
            }
        }
        if ([] === $items) {
            throw new \InvalidArgumentException('No card to export answers.');
        }

        return $items;
    }

    // A path the source gives, kept under the project
    private static function assertRelative(string $path): void
    {
        if ('' === $path || str_starts_with($path, '/') || str_contains($path, '..') || str_starts_with($path, 'public/')) {
            throw new \InvalidArgumentException(\sprintf('Export path refused: "%s". Expected a path relative to the project, outside public/.', $path));
        }
    }

    // The pages an item is shot from
    /** @return list<string> */
    private static function pages(PdfItem | SocialItem $item): array
    {
        return $item instanceof SocialItem ? [$item->postHtml, $item->reelHtml] : [$item->html];
    }

    // What an item says beside its pages - the faces drawn, the manifest entry - which a run has to redo when it changes as much as a page
    /** @return array<mixed> */
    private static function meta(PdfItem | SocialItem $item): array
    {
        return $item instanceof SocialItem ? [$item->manifest, $item->manifestEntry(self::DURATION)] : $item->faces;
    }

    // The folders made, then the item shot
    private function produce(PdfItem | SocialItem $item, string $workDir): void
    {
        foreach ($item->outputs() as $output) {
            $this->filesystem->mkdir(\dirname($this->absolute($output)));
        }
        $item instanceof SocialItem ? $this->social($item, $workDir) : $this->pdf($item, $workDir);
    }

    // One document printed to PDF, then its faces drawn at 600 dpi, the folder they go to emptied first so a face no longer there leaves with it
    private function pdf(PdfItem $item, string $workDir): void
    {
        $browser = $this->renderer->browser() ?? throw new \RuntimeException(\sprintf('No browser to render the pages. Expected one of: %s.', implode(', ', PageRenderer::BROWSERS)));
        $page = $workDir . '/page.html';
        $pdf = $this->absolute($item->path);
        $this->filesystem->dumpFile($page, $item->html);
        $this->renderer->run([$browser, '--headless', '--disable-gpu', '--no-sandbox', '--no-pdf-header-footer', '--print-to-pdf=' . $pdf, 'file://' . $page]);
        if ([] === $item->faces) {
            return;
        }

        $ghostscript = $this->renderer->program('gs') ?? throw new \RuntimeException('Ghostscript (gs) is needed for the images: it draws the JPG from the PDF, at the exact size.');
        $directory = $this->absolute($item->imageDirectory());
        $this->filesystem->remove($directory);
        $this->filesystem->mkdir($directory);

        // The two alpha options: Ghostscript renders with no anti-aliasing unless told, and the text comes out with its edges in steps
        $this->renderer->run([$ghostscript, '-q', '-dNOPAUSE', '-dBATCH', '-sDEVICE=jpeg', '-r600', '-dJPEGQ=95', '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4', '-sOutputFile=' . $directory . '/page-%04d.jpg', $pdf]);
        self::nameFaces($directory, $item->faces);
    }

    // Ghostscript numbers its pages and knows nothing of cards: the order is the PDF's, so the rank gives each face its name, a page past the faces or left null being dropped
    /** @param list<string|null> $faces */
    public static function nameFaces(string $directory, array $faces): void
    {
        foreach (glob($directory . '/page-*.jpg') ?: [] as $page) {
            $face = $faces[(int) substr(basename($page, '.jpg'), 5) - 1] ?? null;
            null === $face ? unlink($page) : rename($page, $directory . '/' . $face . '.jpg');
        }
    }

    // The still image, then the video one frame after the other, the frames put together with the silent sound track the networks ask for
    private function social(SocialItem $item, string $workDir): void
    {
        $ffmpeg = $this->renderer->program('ffmpeg') ?? throw new \RuntimeException('ffmpeg is needed to put the videos together.');
        $chrome = $this->chrome($workDir);

        $chrome->open($this->page($workDir, 'post', $item->postHtml), self::POST_WIDTH, self::POST_HEIGHT);
        $chrome->evaluate('still()');
        $chrome->screenshot($this->absolute($item->jpg()), 95);

        $frames = $workDir . '/frames';
        $this->filesystem->remove($frames);
        $this->filesystem->mkdir($frames);
        $chrome->open($this->page($workDir, 'reel', $item->reelHtml), self::REEL_WIDTH, self::REEL_HEIGHT);
        for ($index = 0; $index < self::frameCount(); ++$index) {
            $chrome->evaluate(\sprintf('frame(%.4F)', $index / self::FPS));
            $chrome->screenshot(\sprintf('%s/%04d.jpg', $frames, $index));
        }
        $this->renderer->run([
            $ffmpeg, '-y', '-loglevel', 'error',
            '-framerate', (string) self::FPS, '-i', $frames . '/%04d.jpg',
            '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo',
            '-shortest', '-c:v', 'libx264', '-preset', 'medium', '-crf', '18', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '64k', '-movflags', '+faststart',
            $this->absolute($item->mp4()),
        ]);
    }

    // The frames of one video
    public static function frameCount(): int
    {
        return (int) round(self::DURATION * self::FPS);
    }

    // Chrome launched at the first card that needs it, and kept open for the others
    private function chrome(string $workDir): ChromeSession
    {
        $browser = $this->renderer->browser() ?? throw new \RuntimeException(\sprintf('No browser to render the pages. Expected one of: %s.', implode(', ', PageRenderer::BROWSERS)));

        return $this->chrome ??= new ChromeSession($browser, $workDir . '/profile');
    }

    // One page written to disk, its address returned
    private function page(string $workDir, string $name, string $html): string
    {
        $path = \sprintf('%s/%s.html', $workDir, $name);
        $this->filesystem->dumpFile($path, $html);

        return 'file://' . $path;
    }

    // The files of the items no longer exported removed, the folders they leave empty with them
    /** @param list<string> $keys */
    private function prune(ExportState $state, array $keys): int
    {
        $removed = 0;
        foreach ($state->prune($keys) as $output) {
            $file = $this->absolute($output);
            if (is_file($file)) {
                $this->filesystem->remove($file);
                ++$removed;
            }
            $directory = \dirname($file);
            if (is_dir($directory) && [] === array_diff((array) scandir($directory), ['.', '..'])) {
                $this->filesystem->remove($directory);
            }
        }

        return $removed;
    }

    // Each manifest written from the state, every card it lists, made by this run or an earlier one
    private function writeManifests(ExportState $state): void
    {
        foreach ($state->manifests() as $manifest) {
            $this->filesystem->dumpFile($this->absolute($manifest), json_encode($state->manifest($manifest), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n");
        }
    }

    // The path as the project reads it
    public function relative(string $path): string
    {
        return ltrim(str_replace($this->projectDir, '', $path), '/');
    }

    private function absolute(string $path): string
    {
        return $this->projectDir . '/' . $path;
    }

    // Each item told to whoever asked - the console, the log - and to the tile
    private function notify(?\Closure $notify, string $message): void
    {
        $this->status->step($message);
        if (null !== $notify) {
            $notify($message);
        }
    }
}
