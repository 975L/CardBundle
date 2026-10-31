<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Management;

use c975L\CardBundle\Entity\CollectedBack;
use c975L\CardBundle\Entity\CollectedCard;
use c975L\CardBundle\Repository\CollectedBackRepository;
use c975L\CardBundle\Repository\CollectedCardRepository;
use c975L\CardBundle\Service\CardCatalog;
use c975L\CardBundle\Service\CardHolder;
use c975L\ConfigBundle\Account\AccountDataProviderInterface;
use c975L\ConfigBundle\Contract\UserInterface;

// The cards part of a member's data export (ConfigBundle's /account/export): every card and every back held. The "Ma collection" section of the account page is the site's own, its count and its link being the site's collection
class AccountDataProvider implements AccountDataProviderInterface
{
    public function __construct(
        private readonly CollectedCardRepository $collectedCardRepository,
        private readonly CollectedBackRepository $collectedBackRepository,
        private readonly CardCatalog $cardCatalog,
    ) {
    }

    // Under "cards" and "backs", oldest first, nothing at all for an account that holds none
    public function getAccountData(UserInterface $user): array
    {
        $holder = CardHolder::ofUser($user);
        $data = [];

        $cards = $this->collectedCardRepository->findByHolder($holder);
        if ([] !== $cards) {
            $data['cards'] = array_map(fn (CollectedCard $collected): array => [
                'card' => $collected->getReference(),
                'name' => $this->cardCatalog->cardName($collected->getReference()),
                'finish' => $collected->getFinish()->value,
                'source' => $collected->getSource(),
                'date' => $collected->getCreatedAt()->format(\DATE_ATOM),
            ], $cards);
        }

        $backs = $this->collectedBackRepository->findByHolder($holder);
        if ([] !== $backs) {
            $data['backs'] = array_map(fn (CollectedBack $collected): array => [
                'back' => $collected->getBack()->getName(),
                'chosen' => $collected->isChosen(),
                'source' => $collected->getSource(),
                'date' => $collected->getCreatedAt()->format(\DATE_ATOM),
            ], $backs);
        }

        return $data;
    }
}
