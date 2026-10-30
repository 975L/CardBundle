<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Entity\CollectedCard;
use c975L\CardBundle\Enum\CardFinish;
use c975L\CardBundle\Repository\CollectedCardRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

// The one door a card enters a collection by, whatever gave it - a quiz, a draw, a parcours. Which card and which finish is the site's decision, taken before calling: a site giving a finish only over the one below checks CardFinish::requires() itself
class CardCollector
{
    public function __construct(
        private readonly CollectedCardRepository $collectedCardRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Gives a card in a finish and returns the holding, null when that finish is held already - the same gift sent twice at once included, the unique index refusing the second (Doctrine then closes the EntityManager, so nothing else is written in that request)
    public function grant(string $holder, string $reference, CardFinish $finish, string $source): ?CollectedCard
    {
        if (null !== $this->collectedCardRepository->findOneBy(['holder' => $holder, 'reference' => $reference, 'finish' => $finish])) {
            return null;
        }

        $collected = new CollectedCard($holder, $reference, $finish, $source);
        $this->entityManager->persist($collected);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        return $collected;
    }
}
