<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Contract\HolderStoreInterface;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Service\FavoriteService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// Whose collection a call is about: the account when one is signed in (the site's session, or the app's token), else the token the device made up - the very key UiBundle holds favorites and plays under (see FavoriteService::resolveHolder()), so a player has one identity across the three
class CardHolder
{
    /** @param iterable<HolderStoreInterface> $stores */
    public function __construct(
        private readonly FavoriteService $favoriteService,
        private readonly Security $security,
        #[AutowireIterator('c975l.card_holder_store')]
        private readonly iterable $stores,
    ) {
    }

    // The key, null for a caller with neither an account nor a usable token. What the device held is handed over to the account on the way, every call doing it rather than a login listener: the token lives in the device's own storage, which no login event sees
    public function resolve(?string $token): ?string
    {
        $holder = $this->favoriteService->resolveHolder($token);
        if (null !== $holder && null !== $token && $holder !== $token && 1 === preg_match(FavoriteService::TOKEN_PATTERN, $token)) {
            foreach ($this->stores as $store) {
                // Read before writing: a member whose device keeps its token would otherwise lock the table on every call for nothing
                if ($store->hasHolder($token)) {
                    $store->moveHolder($token, $holder);
                }
            }
        }

        return $holder;
    }

    // The key of an account, as the collection holds it
    public static function ofUser(UserInterface $user): string
    {
        return 'u' . $user->getId();
    }

    // Whether the caller is signed in, the collection then being safe from a lost phone - an account not saved yet has no key, its collection still being the device's
    public function isAccount(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface && null !== $user->getId();
    }
}
