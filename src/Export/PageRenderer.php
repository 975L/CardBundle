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
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

// What every offline rendering of the cards owes to the disk rather than to the site: the browser found on the machine, the site's stylesheets copied into the page and its paths put back onto the disk. The site's source calls it while rendering its pages (see CardExportSourceInterface), the export while shooting them. Local only, Chrome, Ghostscript and ffmpeg being no Composer dependency
class PageRenderer
{
    // The accepted binaries, in the order they are looked for
    public const array BROWSERS = ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser'];

    // What the PATH gave, false for a program it lacks: the answer does not move while the process runs, and the dashboard asks at each display
    /** @var array<string, string|false> */
    private array $found = [];

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // The first browser installed, whatever name the distribution gives it
    public function browser(): ?string
    {
        foreach (self::BROWSERS as $candidate) {
            $path = $this->program($candidate);
            if (null !== $path) {
                return $path;
            }
        }

        return null;
    }

    // Any other program the export needs, null when the machine lacks it
    public function program(string $name): ?string
    {
        $path = $this->found[$name] ??= new ExecutableFinder()->find($name) ?? false;

        return false === $path ? null : $path;
    }

    // The project's stylesheets, read in the order given and their urls put back onto the disk. On screen by default: the site's print rules lay a web page onto A4 in black ink, where a card is its background. A missing file is skipped - the built ones only exist once the cache is warmed, and a bare page shows at once
    /** @param list<string> $paths relative to the project */
    public function stylesheets(array $paths, bool $screen = true): string
    {
        $css = '';
        foreach ($paths as $path) {
            $file = $this->projectDir . '/' . $path;
            if (is_file($file)) {
                $css .= (string) file_get_contents($file) . "\n";
            }
        }
        $public = 'file://' . $this->projectDir . '/public/';
        // A protocol-relative url (//cdn...) is no path of the site's
        $css = (string) preg_replace('#url\((["\']?)/(?!/)#', 'url($1' . $public, $css);

        return $screen ? self::withoutPrintRules($css) : $css;
    }

    // What every page owes to the disk rather than to the site
    public function onDisk(string $html): string
    {
        // Chrome does not load an offscreen image, and a whole series puts very nearly all of them offscreen
        $html = str_replace('loading="lazy"', 'loading="eager"', $html);

        // An AssetMapper file is served under a digested name that only exists on disk once compiled: its source is read instead. The digest may hold a dash ("carton-fin-en-2ucDF-q.webp")
        $html = (string) preg_replace_callback(
            '#src="/assets/([^"]+)-[A-Za-z0-9_-]{7}\.(\w+)"#',
            fn (array $matches): string => is_file($source = $this->projectDir . '/assets/' . $matches[1] . '.' . $matches[2]) ? 'src="file://' . $source . '"' : $matches[0],
            $html
        );

        return (string) preg_replace('#src="/(?!/)#', 'src="file://' . $this->projectDir . '/public/', $html);
    }

    // One external program run to its end, its output told when it fails
    /** @param list<string> $command */
    public function run(array $command): void
    {
        // A series of illustrated cards in three finishes takes Chrome well past the default 60 seconds
        $process = new Process($command, timeout: 600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(\sprintf("\"%s\" failed:\n%s", $command[0], trim($process->getErrorOutput() . $process->getOutput())));
        }
    }

    // The print blocks dropped, braces counted one by one: an @media block holds as many as it has rules
    public static function withoutPrintRules(string $css): string
    {
        $offset = 0;
        while (false !== $start = stripos($css, '@media print', $offset)) {
            $open = strpos($css, '{', $start);
            if (false === $open) {
                break;
            }
            $css = substr($css, 0, $start) . substr($css, self::closingBrace($css, $open) + 1);
            $offset = $start;
        }

        return $css;
    }

    // Where the block opened at $open ends
    private static function closingBrace(string $css, int $open): int
    {
        $depth = 1;
        $index = $open + 1;
        $length = \strlen($css);
        while ($index < $length) {
            $depth += match ($css[$index]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            if (0 === $depth) {
                return $index;
            }
            ++$index;
        }

        return $length - 1;
    }
}
