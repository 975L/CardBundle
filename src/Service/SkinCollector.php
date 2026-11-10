<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CollectedSkin;
use c975L\CardBundle\Repository\CollectedSkinRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

// The one door a skin enters a collection by, pack by pack, and the one it is chosen through, as BackCollector is for the backs: a reward, a purchase or a code all give it here and never on the phone
class SkinCollector
{
    public function __construct(
        private readonly CollectedSkinRepository $collectedSkinRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Gives a skin for one pack, and says whether it was given: false for a pack it has no picture in, when already held there or not published - the same gift sent twice at once included, the unique index refusing the second (Doctrine then closes the EntityManager, so nothing else is written in that request). A paid skin is given even unpublished or with its pictures of that pack gone since, a purchase being owed
    public function grant(string $holder, CardSkin $skin, string $pack, string $source, bool $paid = false): bool
    {
        if ((!$paid && (!$skin->isPublished() || !$skin->dresses($pack))) || $this->holds($holder, $skin, $pack)) {
            return false;
        }

        $this->entityManager->persist(new CollectedSkin($holder, $skin, $pack, $source));
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    // Takes back a skin the given source gave for a pack, and says whether one was held: a refunded purchase. The same skin earned another way stays, and a chosen one taken back leaves the pack's cards to their own pictures
    public function revoke(string $holder, CardSkin $skin, string $pack, string $source): bool
    {
        $collected = $this->collectedSkinRepository->findOneBy(['holder' => $holder, 'skin' => $skin, 'pack' => $pack, 'source' => $source]);
        if (null === $collected) {
            return false;
        }

        $this->entityManager->remove($collected);
        $this->entityManager->flush();

        return true;
    }

    // Makes a skin held for a pack the one that pack's cards wear, or none - their own pictures - and says whether it was done: false for a skin not held there or not published. The other packs keep their choice
    public function choose(string $holder, string $pack, ?CardSkin $skin): bool
    {
        $held = array_filter($this->collectedSkinRepository->findByHolder($holder), static fn (CollectedSkin $collected): bool => $pack === $collected->getPack());
        if (null !== $skin && (!$skin->isPublished() || [] === array_filter($held, static fn (CollectedSkin $collected): bool => $collected->getSkin() === $skin))) {
            return false;
        }

        foreach ($held as $collected) {
            $collected->setChosen($collected->getSkin() === $skin);
        }
        $this->entityManager->flush();

        return true;
    }

    // The skin each pack's cards wear, by pack, in one query for the whole collection: a pack with none chosen - or one unpublished since, worn again once republished - is left out
    /** @return array<string, CardSkin> */
    public function chosen(string $holder): array
    {
        $chosen = [];
        foreach ($this->collectedSkinRepository->findByHolder($holder) as $collected) {
            if ($collected->isChosen() && $collected->getSkin()->isPublished()) {
                $chosen[$collected->getPack()] = $collected->getSkin();
            }
        }

        return $chosen;
    }

    // Whether the holder has that skin for that pack, whichever way they got it
    public function holds(string $holder, CardSkin $skin, string $pack): bool
    {
        return null !== $this->collectedSkinRepository->findOneBy(['holder' => $holder, 'skin' => $skin, 'pack' => $pack]);
    }
}
