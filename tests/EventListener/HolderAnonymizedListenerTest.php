<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\EventListener;

use c975L\CardBundle\Contract\HolderStoreInterface;
use c975L\CardBundle\EventListener\HolderAnonymizedListener;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use PHPUnit\Framework\TestCase;

class HolderAnonymizedListenerTest extends TestCase
{
    // Every store forgets the account, the site's own as much as the bundle's
    public function testEveryStoreForgetsTheAccount(): void
    {
        $stores = [];
        foreach ([1, 2] as $i) {
            $store = $this->createMock(HolderStoreInterface::class);
            $store->expects($this->once())->method('deleteByHolder')->with('u9');
            $stores[] = $store;
        }
        $user = $this->createStub(InactivityAwareInterface::class);
        $user->method('getId')->willReturn(9);

        new HolderAnonymizedListener($stores)(new UserAnonymizedEvent($user));
    }
}
