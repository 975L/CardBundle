<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Export;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

// Where an export stands, read by the dashboard tile from another process than the one running it: the order dropped at the click, the step written as the run goes, the failure left when it does not reach its end. Under the environment's own var/ folder, so a test suite posting the tile leaves no trace on the dashboard the developer is looking at
class ExportStatus
{
    private const string STEP_FILE = 'card-export.etape';

    // Dropped by the tile and removed by the run as it starts: what is left of it says no worker took the order
    private const string REQUEST_FILE = 'card-export.demande';

    private const string FAILURE_FILE = 'card-export.echec';

    // The words the status writes for itself rather than a file or a message, translated by the tile (see CardExportShortcutProvider)
    public const string PREPARING = 'preparing';
    public const string STALE = 'stale:';

    // Past this silence a step or an order is one whose worker was killed or never came, a single render being cut at ten minutes (see PageRenderer::run())
    private const int STALE_AFTER = 1800;

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        $this->filesystem = new Filesystem();
    }

    // The order noted at the click, before anything is queued
    public function request(): void
    {
        $this->filesystem->remove($this->path(self::FAILURE_FILE));
        $this->filesystem->dumpFile($this->path(self::REQUEST_FILE), $this->now('H:i'));
    }

    // A run under way, at the step it says - written before the order is removed, so the tile never finds neither
    public function step(string $step): void
    {
        $this->filesystem->dumpFile($this->path(self::STEP_FILE), $step);
        $this->filesystem->remove([$this->path(self::REQUEST_FILE), $this->path(self::FAILURE_FILE)]);
    }

    // The run over, whichever way it ended
    public function finish(): void
    {
        $this->filesystem->remove($this->path(self::STEP_FILE));
    }

    // What a run leaves of itself when it does not reach its end, the order and the step it may have left going with it: wherever it broke, the failure is what the tile shows
    public function fail(string $message): void
    {
        $this->filesystem->remove([$this->path(self::REQUEST_FILE), $this->path(self::STEP_FILE)]);
        $this->filesystem->dumpFile($this->path(self::FAILURE_FILE), \sprintf('%s - %s', $this->now('H:i'), $message));
    }

    // Whether a run is under way or waiting for its worker, the only state that forbids another - a failure does not
    public function isRunning(): bool
    {
        return null !== $this->current() && 'failed' !== $this->current()['state'];
    }

    // Where the export stands, null when nothing is under way and the last run did not fail
    /** @return array{state: 'running'|'requested'|'failed', detail: string}|null */
    public function current(): ?array
    {
        $this->expireStale();
        foreach (['running' => self::STEP_FILE, 'requested' => self::REQUEST_FILE, 'failed' => self::FAILURE_FILE] as $state => $file) {
            if (is_file($this->path($file))) {
                return ['state' => $state, 'detail' => trim((string) file_get_contents($this->path($file)))];
            }
        }

        return null;
    }

    // The hour as the one reading it has it on their wall, PHP running in UTC as long as date.timezone is left out of the machine's php.ini
    public function now(string $format): string
    {
        return new \DateTimeImmutable('now', $this->timezone())->format($format);
    }

    // The same reading for a file
    public function localTime(int $timestamp, string $format): string
    {
        return new \DateTimeImmutable('@' . $timestamp)->setTimezone($this->timezone())->format($format);
    }

    // The site's own timezone, PHP's where the entry is missing
    private function timezone(): \DateTimeZone
    {
        $timezone = $this->configService->get('site-timezone');

        return \is_string($timezone) && \in_array($timezone, \DateTimeZone::listIdentifiers(), true)
            ? new \DateTimeZone($timezone)
            : new \DateTimeZone(date_default_timezone_get());
    }

    // A step or an order left behind by a dead worker turned into a failure, which frees the tile
    private function expireStale(): void
    {
        foreach ([self::STEP_FILE, self::REQUEST_FILE] as $file) {
            $path = $this->path($file);
            if (is_file($path) && time() - (int) filemtime($path) > self::STALE_AFTER) {
                $this->filesystem->dumpFile($this->path(self::FAILURE_FILE), self::STALE . $this->localTime((int) filemtime($path), 'H:i'));
                $this->filesystem->remove($path);
            }
        }
    }

    private function path(string $name): string
    {
        return \sprintf('%s/var/%s/%s', $this->projectDir, $this->environment, $name);
    }
}
