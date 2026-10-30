<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Export;

use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Export\CardExportSourceInterface;
use c975L\CardBundle\Export\ExportStatus;
use c975L\CardBundle\Export\PageRenderer;
use c975L\CardBundle\Export\PdfItem;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class CardExporterTest extends TestCase
{
    private string $project;

    /** @var list<PdfItem> */
    private array $items = [];

    private int $prints = 0;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/card-exporter-' . bin2hex(random_bytes(4));
        mkdir($this->project);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->project);
    }

    // The first run makes everything, the next one nothing, and an edit redoes only the item it touched
    public function testOnlyWhatChangedIsMadeAgain(): void
    {
        $this->items = [new PdfItem('Construction/Export/a.pdf', 'A'), new PdfItem('Construction/Export/b.pdf', 'B')];
        $this->assertSame(['made' => 2, 'kept' => 0, 'removed' => 0], $this->exporter()->export());
        $this->assertSame(['made' => 0, 'kept' => 2, 'removed' => 0], $this->exporter()->export());

        $this->items[1] = new PdfItem('Construction/Export/b.pdf', 'B edited');
        $this->assertSame(['made' => 1, 'kept' => 1, 'removed' => 0], $this->exporter()->export());
        $this->assertSame(3, $this->prints);

        // Forced, everything again
        $this->assertSame(['made' => 2, 'kept' => 0, 'removed' => 0], $this->exporter()->export(force: true));
    }

    // A face renamed or a manifest entry edited, the page left as it was, is a change all the same
    public function testWhatAnItemSaysBesideItsPageIsAChange(): void
    {
        $this->items = [new PdfItem('Construction/Export/a.pdf', 'A', ['hero'])];
        $this->exporter()->export();

        $this->items = [new PdfItem('Construction/Export/a.pdf', 'A', ['hero-front'])];
        $this->assertSame(['made' => 1, 'kept' => 0, 'removed' => 0], $this->exporter()->export());
        $this->assertFileExists($this->project . '/Construction/Export/a/hero-front.jpg');
    }

    // A second run is refused while one is under way: it would empty the folder the first one works in
    public function testARunIsRefusedWhileAnotherIsUnderWay(): void
    {
        $this->items = [new PdfItem('Construction/Export/a.pdf', 'A')];
        $status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');
        $status->step('heros.pdf');

        $this->expectException(\RuntimeException::class);
        $this->exporter()->export();
    }

    // A file removed by hand is made again even though nothing changed
    public function testAMissingFileIsMadeAgain(): void
    {
        $this->items = [new PdfItem('Construction/Export/a.pdf', 'A')];
        $this->exporter()->export();
        unlink($this->project . '/Construction/Export/a.pdf');

        $this->assertSame(['made' => 1, 'kept' => 0, 'removed' => 0], $this->exporter()->export());
    }

    // A full run removes what the site no longer exports, its faces and their emptied folder with it; a targeted run never does
    public function testAFullRunRemovesWhatIsNoLongerExported(): void
    {
        $this->items = [new PdfItem('Construction/Export/a.pdf', 'A'), new PdfItem('Construction/Export/b.pdf', 'B', ['b-normal'])];
        $this->exporter()->export();
        $this->assertFileExists($this->project . '/Construction/Export/b/b-normal.jpg');

        $this->items = [new PdfItem('Construction/Export/a.pdf', 'A')];
        $this->assertSame(0, $this->exporter()->export('a')['removed']);
        $this->assertFileExists($this->project . '/Construction/Export/b.pdf');

        $this->assertSame(2, $this->exporter()->export()['removed']);
        $this->assertFileDoesNotExist($this->project . '/Construction/Export/b.pdf');
        $this->assertDirectoryDoesNotExist($this->project . '/Construction/Export/b');
        $this->assertFileExists($this->project . '/Construction/Export/a.pdf');
    }

    // Two pages writing the same file, or a path out of the project, are the site's mistake and refused before anything is shot
    public function testAPathSharedOrOutOfTheProjectIsRefused(): void
    {
        foreach ([[new PdfItem('Construction/a.pdf', 'A'), new PdfItem('Construction/a.pdf', 'B')], [new PdfItem('../a.pdf', 'A')], [new PdfItem('public/a.pdf', 'A')]] as $items) {
            $this->items = $items;
            try {
                $this->exporter()->export();
                $this->fail('Refused');
            } catch (\InvalidArgumentException) {
                $this->assertSame(0, $this->prints);
            }
        }
    }

    // Ghostscript's pages take the names of the faces, a page left null or past them going
    public function testTheFacesAreNamedInTheirOrder(): void
    {
        foreach ([1, 2, 3, 4] as $page) {
            touch(\sprintf('%s/page-%04d.jpg', $this->project, $page));
        }

        CardExporter::nameFaces($this->project, ['a-normal', null, 'a-shiny']);

        $this->assertSame([$this->project . '/a-normal.jpg', $this->project . '/a-shiny.jpg'], glob($this->project . '/*.jpg'));
    }

    private function exporter(): CardExporter
    {
        $source = $this->createStub(CardExportSourceInterface::class);
        $source->method('getExportItems')->willReturnCallback(fn (): array => $this->items);

        // Chrome writes the PDF it is asked for, Ghostscript one page per face
        $renderer = $this->createStub(PageRenderer::class);
        $renderer->method('browser')->willReturn('chrome');
        $renderer->method('program')->willReturn('gs');
        $renderer->method('run')->willReturnCallback(function (array $command): void {
            foreach ($command as $argument) {
                if (str_starts_with($argument, '--print-to-pdf=')) {
                    file_put_contents(substr($argument, 15), 'pdf');
                    ++$this->prints;
                }
                if (str_starts_with($argument, '-sOutputFile=')) {
                    touch(\sprintf(substr($argument, 13), 1));
                }
            }
        });

        $status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');

        return new CardExporter([$source], $renderer, $status, $this->project, 'test');
    }
}
