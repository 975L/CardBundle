<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Management;

use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Export\ExportStatus;
use c975L\CardBundle\Management\CardExportShortcutProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class CardExportShortcutProviderTest extends TestCase
{
    private string $project;

    private ExportStatus $status;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/card-shortcut-' . bin2hex(random_bytes(4));
        $this->status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->project);
    }

    // No tile where the export cannot run, the production server having neither Chrome nor Ghostscript
    public function testNoTileWhereTheExportCannotRun(): void
    {
        $this->assertSame([], $this->provider(false)->getShortcuts());
    }

    // The tile's label says where the export stands, the status's own words translated and a failure's message shown as it is
    public function testTheLabelFollowsTheRun(): void
    {
        $this->assertSame('label.export_cards', $this->label());

        $this->status->step(ExportStatus::PREPARING);
        $this->assertSame('label.export_cards (label.export_running[label.export_preparing])', $this->label());

        $this->status->step('heros.pdf');
        $this->assertSame('label.export_cards (label.export_running[heros.pdf])', $this->label());

        $this->status->fail('gs');
        $this->assertStringStartsWith('label.export_cards (label.export_failed[', $this->label());

        $this->status->step('heros.pdf');
        touch($this->project . '/var/test/card-export.etape', time() - 3600);
        $this->assertSame('label.export_cards (label.export_failed[label.export_stale[' . $this->status->localTime(time() - 3600, 'H:i') . ']])', $this->label());

        $this->status->request();
        $this->assertStringStartsWith('label.export_cards (label.export_requested[', $this->label());
    }

    // Nothing under way, the date of the last run
    public function testTheLastRunIsDated(): void
    {
        new Filesystem()->dumpFile($this->project . '/' . CardExporter::STATE_FILE, '{}');

        $this->assertStringStartsWith('label.export_cards (label.export_last[', $this->label());
    }

    private function label(): string
    {
        return $this->provider(true)->getShortcuts()[0]['label'];
    }

    private function provider(bool $available): CardExportShortcutProvider
    {
        $cardExporter = $this->createStub(CardExporter::class);
        $cardExporter->method('isAvailable')->willReturn($available);

        // The key, its parameters in brackets: what is translated shows, and with what
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => $id . ([] === $parameters ? '' : '[' . implode(',', $parameters) . ']'));

        return new CardExportShortcutProvider($cardExporter, $this->status, $translator, $this->project);
    }
}
