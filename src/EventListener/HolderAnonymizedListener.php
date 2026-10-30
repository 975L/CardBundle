<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\EventListener;

use c975L\CardBundle\Contract\HolderStoreInterface;
use c975L\CardBundle\Service\CardHolder;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

// An account deleted or anonymized loses its collection: it is held under "u<id>", a key no foreign key ties to the account, so nothing would erase it otherwise - every store, the site's own included (see HolderStoreInterface)
#[AsEventListener]
class HolderAnonymizedListener
{
    /** @param iterable<HolderStoreInterface> $stores */
    public function __construct(
        #[AutowireIterator('c975l.card_holder_store')]
        private readonly iterable $stores,
    ) {
    }

    // Erases everything the account just anonymized held
    public function __invoke(UserAnonymizedEvent $event): void
    {
        $holder = CardHolder::ofUser($event->user);
        foreach ($this->stores as $store) {
            $store->deleteByHolder($holder);
        }
    }
}
