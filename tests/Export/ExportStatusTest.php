<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Export;

use c975L\CardBundle\Export\ExportStatus;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ExportStatusTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/card-status-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->project);
    }

    // The order, then the run, then nothing: what the tile reads at each moment, a failure not keeping another run from starting
    public function testTheTileFollowsTheRun(): void
    {
        $status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');
        $this->assertNull($status->current());

        $status->request();
        $this->assertSame('requested', $status->current()['state'] ?? null);
        $this->assertTrue($status->isRunning());

        $status->step('heros.pdf');
        $this->assertSame(['state' => 'running', 'detail' => 'heros.pdf'], $status->current());

        $status->finish();
        $this->assertNull($status->current());

        $status->fail('gs');
        $this->assertSame('failed', $status->current()['state'] ?? null);
        $this->assertFalse($status->isRunning());
    }

    // A failure wins wherever the run broke, an order or a step it left behind going with it
    public function testAFailureClearsTheOrderAndTheStep(): void
    {
        $status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');
        $status->request();
        $status->fail('No card to export answers.');
        $this->assertSame('failed', $status->current()['state'] ?? null);

        $status->step('heros.pdf');
        $status->fail('gs');
        $this->assertSame('failed', $status->current()['state'] ?? null);
        $this->assertFalse($status->isRunning());
    }

    // A step nobody has touched for half an hour is a dead worker's, turned into a failure
    public function testAStaleStepBecomesAFailure(): void
    {
        $status = new ExportStatus($this->createStub(ConfigServiceInterface::class), $this->project, 'test');
        $status->step('heros.pdf');
        touch($this->project . '/var/test/card-export.etape', time() - 3600);

        $this->assertSame(['state' => 'failed', 'detail' => ExportStatus::STALE . $status->localTime(time() - 3600, 'H:i')], $status->current());
    }
}
