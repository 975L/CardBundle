<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules replayed on the bundle's code (see ConfigBundle's CodeRulesTestCase)
class CodeRulesTest extends CodeRulesTestCase
{
    // Where the bundle's classes live
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    // The namespace of those classes
    protected function sourceNamespace(): string
    {
        return 'c975L\CardBundle';
    }

    // A holder's collection is read live for that holder alone, never cached, so its bulk moves and erasures have nothing to invalidate
    protected function allowed(): array
    {
        $reason = 'A holder\'s collection is read live for that holder alone, never cached';
        $allowed = [];
        foreach (['CollectedBack', 'CollectedCard', 'CollectedSkin'] as $entity) {
            foreach (['moveHolder', 'deleteByHolder'] as $method) {
                $allowed['c975L\\CardBundle\\Repository\\' . $entity . 'Repository::' . $method] = $reason;
            }
        }

        return [self::BULK_QUERY => $allowed];
    }
}
