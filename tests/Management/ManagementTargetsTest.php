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
use c975L\CardBundle\Management\CardGuidedProjectProvider;
use c975L\CardBundle\Management\MenuProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        // The tile and the export project only show where the export can run
        $cardExporter = $this->createStub(CardExporter::class);
        $cardExporter->method('isAvailable')->willReturn(true);

        return [
            new MenuProvider($this->createStub(ConfigServiceInterface::class)),
            // The guided projects generate their urls, so they take the recorders this test case reads them back from
            new CardGuidedProjectProvider($this->adminUrlGenerator(), $this->createStub(ConfigServiceInterface::class), $this->urlGenerator(), $cardExporter),
            new CardExportShortcutProvider($cardExporter, $this->createStub(ExportStatus::class), $this->createStub(TranslatorInterface::class), sys_get_temp_dir()),
        ];
    }

    #[\Override]
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller/Management'];
    }
}
