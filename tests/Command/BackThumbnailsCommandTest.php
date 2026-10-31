<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Command;

use c975L\CardBundle\Command\BackThumbnailsCommand;
use c975L\CardBundle\Service\BackThumbnailMaker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

class BackThumbnailsCommandTest extends TestCase
{
    use ConsoleIoTrait;

    // Without Chrome or a page to shoot, nothing is attempted and the command fails
    public function testUnavailableFails(): void
    {
        $backThumbnailMaker = $this->createMock(BackThumbnailMaker::class);
        $backThumbnailMaker->method('isAvailable')->willReturn(false);
        $backThumbnailMaker->expects($this->never())->method('make');

        $this->assertSame(Command::FAILURE, new BackThumbnailsCommand($backThumbnailMaker)($this->io($output)));
        $this->assertStringContainsString('CardBackPageProviderInterface', $output->fetch());
    }

    // Each back shot is listed with its image, one the site cannot show alone said so, the slugs and delay handed over as typed
    public function testEachBackIsListed(): void
    {
        $backThumbnailMaker = $this->createMock(BackThumbnailMaker::class);
        $backThumbnailMaker->method('isAvailable')->willReturn(true);
        $backThumbnailMaker->expects($this->once())->method('make')->with(['dawn', 'night'], 1000)
            ->willReturn(['dawn' => 'medias/cards/backs/dawn.webp', 'night' => null]);

        $this->assertSame(Command::SUCCESS, new BackThumbnailsCommand($backThumbnailMaker)($this->io($output), ['dawn', 'night'], 1000));
        $display = $output->fetch();
        $this->assertStringContainsString('dawn : medias/cards/backs/dawn.webp', $display);
        $this->assertStringContainsString('night : pas de page', $display);
        $this->assertStringNotContainsString('Aucun dos publié', $display);
    }

    // A slug matching no published variant is named rather than silently skipped
    public function testAnUnknownSlugIsWarned(): void
    {
        $backThumbnailMaker = $this->createStub(BackThumbnailMaker::class);
        $backThumbnailMaker->method('isAvailable')->willReturn(true);
        $backThumbnailMaker->method('make')->willReturn(['dawn' => 'medias/cards/backs/dawn.webp']);

        $this->assertSame(Command::SUCCESS, new BackThumbnailsCommand($backThumbnailMaker)($this->io($output), ['dawn', 'dwan']));
        $this->assertStringContainsString('Aucun dos publié pour : dwan', $output->fetch());
    }
}
