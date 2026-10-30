<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\MessageHandler;

use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Export\ExportStatus;
use c975L\CardBundle\Message\ExportCardsMessage;
use c975L\CardBundle\MessageHandler\ExportCardsHandler;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class ExportCardsHandlerTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/card-handler-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->project);
    }

    // A failure is left for the tile rather than thrown back to Messenger, the order it was given for going with it
    public function testAFailureIsLeftForTheTile(): void
    {
        $status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');
        $status->request();
        $cardExporter = $this->createStub(CardExporter::class);
        $cardExporter->method('export')->willThrowException(new \InvalidArgumentException('No card to export answers.'));

        new ExportCardsHandler($cardExporter, $status, new NullLogger())(new ExportCardsMessage());

        $this->assertSame('failed', $status->current()['state'] ?? null);
        $this->assertStringEndsWith('No card to export answers.', $status->current()['detail'] ?? '');
    }

    // A run reaching its end leaves nothing for the tile to show but its date
    public function testARunReachingItsEndLeavesNoState(): void
    {
        $status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');
        $cardExporter = $this->createStub(CardExporter::class);
        $cardExporter->method('export')->willReturn(['made' => 1, 'kept' => 0, 'removed' => 0]);

        new ExportCardsHandler($cardExporter, $status, new NullLogger())(new ExportCardsMessage());

        $this->assertNull($status->current());
    }
}
