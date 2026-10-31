<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

// A command's io over a buffer the test reads back
trait ConsoleIoTrait
{
    private function io(?BufferedOutput &$output): SymfonyStyle
    {
        $output = new BufferedOutput();

        return new SymfonyStyle(new ArrayInput([]), $output);
    }
}
