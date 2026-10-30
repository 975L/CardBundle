<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CollectedBack;
use c975L\CardBundle\Repository\CollectedBackRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

// The one door a card back enters a collection by, and the one it is chosen through, as CardCollector is for the cards: a reward, a purchase or a code all give it here and never on the phone
class BackCollector
{
    public function __construct(
        private readonly CollectedBackRepository $collectedBackRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Gives a back, and says whether it was given: false when already held or not published - the same gift sent twice at once included, the unique index refusing the second (Doctrine then closes the EntityManager, so nothing else is written in that request). A paid back is given even unpublished, a purchase being owed: it stays out of sight until republished (see chosen())
    public function grant(string $holder, CardBack $back, string $source, bool $paid = false): bool
    {
        if ((!$paid && !$back->isPublished()) || null !== $this->collectedBackRepository->findOneBy(['holder' => $holder, 'back' => $back])) {
            return false;
        }

        $this->entityManager->persist(new CollectedBack($holder, $back, $source));
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    // Takes back a back the given source gave, and says whether one was held: a refunded purchase. The same back earned another way stays, and a chosen one taken back leaves its pack to its own picture
    public function revoke(string $holder, CardBack $back, string $source): bool
    {
        $collected = $this->collectedBackRepository->findOneBy(['holder' => $holder, 'back' => $back, 'source' => $source]);
        if (null === $collected) {
            return false;
        }

        $this->entityManager->remove($collected);
        $this->entityManager->flush();

        return true;
    }

    // Makes a held back the one its pack's cards wear, or none for the pack's own picture, and says whether it was done: false for a back not held, not published or of another pack
    public function choose(string $holder, string $pack, ?CardBack $back): bool
    {
        $held = $this->collectedBackRepository->findByHolder($holder);
        if (null !== $back && (!$back->isPublished() || $back->getPack() !== $pack || [] === array_filter($held, static fn (CollectedBack $collected): bool => $collected->getBack() === $back))) {
            return false;
        }

        foreach ($held as $collected) {
            if ($collected->getBack()->getPack() === $pack) {
                $collected->setChosen($collected->getBack() === $back);
            }
        }
        $this->entityManager->flush();

        return true;
    }

    // The back each pack's cards wear for the holder, by pack, a pack with none chosen being absent - as is one whose back was unpublished, worn again once republished, the choice being kept
    /** @return array<string, CardBack> */
    public function chosen(string $holder): array
    {
        $chosen = [];
        foreach ($this->collectedBackRepository->findByHolder($holder) as $collected) {
            if ($collected->isChosen() && $collected->getBack()->isPublished()) {
                $chosen[(string) $collected->getBack()->getPack()] = $collected->getBack();
            }
        }

        return $chosen;
    }
}
