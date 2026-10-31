<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Management;

use c975L\CardBundle\Contract\CardBackPageProviderInterface;
use c975L\CardBundle\Controller\Management\BackThumbnailsController;
use c975L\CardBundle\Export\PageRenderer;
use c975L\CardBundle\Management\BackThumbnailsShortcutProvider;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Service\BackThumbnailMaker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BackThumbnailsShortcutProviderTest extends TestCase
{
    // No tile for a site with no page showing a back alone: nothing to shoot
    public function testNoTileWithoutABackPage(): void
    {
        $this->assertSame([], $this->provider([])->getShortcuts());
    }

    // With one, the tile wherever Chrome is - the production server having none - as a POST for the super admin
    public function testTheTileWhereChromeIs(): void
    {
        $page = $this->createStub(CardBackPageProviderInterface::class);
        $shortcuts = $this->provider([$page])->getShortcuts();
        if (null === new PageRenderer(sys_get_temp_dir())->browser()) {
            $this->assertSame([], $shortcuts);

            return;
        }

        $this->assertSame(BackThumbnailsController::ROUTE, $shortcuts[0]['route']);
        $this->assertSame('POST', $shortcuts[0]['method']);
        $this->assertSame(BackThumbnailsController::ROLE, $shortcuts[0]['role']);
    }

    /** @param list<CardBackPageProviderInterface> $pages */
    private function provider(array $pages): BackThumbnailsShortcutProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $maker = new BackThumbnailMaker($pages, $this->createStub(CardBackRepository::class), new PageRenderer(sys_get_temp_dir()), $this->createStub(EntityManagerInterface::class), sys_get_temp_dir());

        return new BackThumbnailsShortcutProvider($maker, $translator);
    }
}
