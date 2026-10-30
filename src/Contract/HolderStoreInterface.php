<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// Whatever a site keeps under a holder's key beside the cards - the backs here, a child's Plumes on Papa Câlin - so CardHolder hands it over to the account a device is connected to, and HolderAnonymizedListener erases it with the account. Implemented by a repository, or by a service when the move has rules of its own (the Plumes, capped per day); autoconfiguration tags it
#[AutoconfigureTag('c975l.card_holder_store')]
interface HolderStoreInterface
{
    // Whether the holder has anything at all, read before a move that would otherwise lock the table for nothing
    public function hasHolder(string $holder): bool;

    // Hands everything of $from over to $to, the account keeping its own where both hold the same thing
    public function moveHolder(string $from, string $to): void;

    // Forgets everything of the holder
    public function deleteByHolder(string $holder): void;
}
