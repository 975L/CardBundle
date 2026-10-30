<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Management;

use c975L\CardBundle\Management\CardGuidedProjectProvider;
use c975L\CardBundle\Management\MenuProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;

class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [
            new MenuProvider($this->createStub(ConfigServiceInterface::class)),
            // The guided project generates its url, so it takes the recorder this test case reads it back from
            new CardGuidedProjectProvider($this->adminUrlGenerator(), $this->createStub(ConfigServiceInterface::class)),
        ];
    }

    #[\Override]
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller/Management'];
    }
}
