<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Contract\CardBackPageProviderInterface;
use c975L\CardBundle\Export\PageRenderer;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Service\BackThumbnailMaker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BackThumbnailMakerTest extends TestCase
{
    // A page to shoot and a browser to shoot it, the two needed
    public function testAvailableOnlyWithAPageAndABrowser(): void
    {
        $page = $this->createStub(CardBackPageProviderInterface::class);

        $this->assertTrue($this->maker([$page], '/usr/bin/chromium')->isAvailable());
        $this->assertFalse($this->maker([], '/usr/bin/chromium')->isAvailable());
        $this->assertFalse($this->maker([$page], null)->isAvailable());
    }

    // No browser: refused before any back is read or any folder made
    public function testMakeWithoutABrowserThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No browser to shoot the backs.');

        $this->maker([$this->createStub(CardBackPageProviderInterface::class)], null)->make();
    }

    /** @param list<CardBackPageProviderInterface> $pageProviders */
    private function maker(array $pageProviders, ?string $browser): BackThumbnailMaker
    {
        $renderer = $this->createStub(PageRenderer::class);
        $renderer->method('browser')->willReturn($browser);

        return new BackThumbnailMaker($pageProviders, $this->createStub(CardBackRepository::class), $renderer, $this->createStub(EntityManagerInterface::class), sys_get_temp_dir());
    }
}
