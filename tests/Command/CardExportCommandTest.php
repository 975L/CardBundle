<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Command;

use c975L\CardBundle\Command\CardExportCommand;
use c975L\CardBundle\Export\CardExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class CardExportCommandTest extends TestCase
{
    // An empty target is everything, a target is handed to the site as it is typed
    public function testTheTargetIsHandedOver(): void
    {
        $cardExporter = $this->createMock(CardExporter::class);
        $cardExporter->expects($this->exactly(2))->method('export')
            ->willReturnCallback(static fn (?string $target, bool $force): array => ['made' => null === $target ? 2 : 1, 'kept' => 0, 'removed' => 0]);
        $command = new CardExportCommand($cardExporter);

        $this->assertSame(Command::SUCCESS, $command($this->io($output), ''));
        $this->assertStringContainsString('2 produits', $output->fetch());
        $this->assertSame(Command::SUCCESS, $command($this->io($output), 'heros/12', true));
        $this->assertStringContainsString('1 produits', $output->fetch());
    }

    // A refused target, a missing binary or a run already under way end the command in failure, their message shown
    public function testAFailureIsShown(): void
    {
        $cardExporter = $this->createStub(CardExporter::class);
        $cardExporter->method('export')->willThrowException(new \RuntimeException('An export is already running.'));

        $this->assertSame(Command::FAILURE, new CardExportCommand($cardExporter)($this->io($output)));
        $this->assertStringContainsString('An export is already running.', $output->fetch());
    }

    // Ctrl+C or a kill release the run before the process exits with the shell's code for a signal
    public function testASignalAbortsTheRun(): void
    {
        $cardExporter = $this->createMock(CardExporter::class);
        $cardExporter->expects($this->once())->method('abort')->with('Interrupted.');
        $command = new CardExportCommand($cardExporter);

        $this->assertSame([\SIGINT, \SIGTERM], $command->getSubscribedSignals());
        $this->assertSame(130, $command->handleSignal(\SIGINT));
    }

    private function io(?BufferedOutput &$output): SymfonyStyle
    {
        $output = new BufferedOutput();

        return new SymfonyStyle(new ArrayInput([]), $output);
    }
}
