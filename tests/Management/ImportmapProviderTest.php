<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Management;

use c975L\CardBundle\Management\ImportmapProvider;
use c975L\CardBundle\Service\AssetProvider;
use PHPUnit\Framework\TestCase;

class ImportmapProviderTest extends TestCase
{
    // No back-office script of its own
    public function testGetAdminImportmapEntriesIsEmpty(): void
    {
        $this->assertSame([], new ImportmapProvider()->getAdminImportmapEntries());
    }

    // The barrel as an entrypoint, under the very name AssetProvider hands the layout
    public function testGetImportmapEntriesDeclaresTheLayoutsScripts(): void
    {
        $entries = new ImportmapProvider()->getImportmapEntries();

        $this->assertSame([
            '@c975l/card-bundle/controllers.js' => [
                'path' => 'assets/controllers.js',
                'entrypoint' => true,
            ],
        ], $entries);
        $this->assertSame(new AssetProvider()->getScripts(), array_keys($entries));
    }
}
