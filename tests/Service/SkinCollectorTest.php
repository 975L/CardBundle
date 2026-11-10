<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CardSkinPicture;
use c975L\CardBundle\Entity\CollectedSkin;
use c975L\CardBundle\Repository\CollectedSkinRepository;
use c975L\CardBundle\Service\SkinCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SkinCollectorTest extends TestCase
{
    use DuplicateGiftTrait;

    // Choosing a skin for a pack marks it and clears the others of that pack, the other packs keeping their own choice
    public function testChoosingASkinClearsOnlyItsPack(): void
    {
        [$toon, $sketch] = [$this->skin('toon'), $this->skin('sketch')];
        $held = [new CollectedSkin('u1', $toon, 'GOB', 'quiz'), new CollectedSkin('u1', $sketch, 'GOB', 'quiz'), new CollectedSkin('u1', $sketch, 'GUE', 'quiz')];
        $held[1]->setChosen(true);
        $held[2]->setChosen(true);

        $this->assertTrue($this->collector($held)->choose('u1', 'GOB', $toon));

        $this->assertSame([true, false, true], array_map(static fn (CollectedSkin $collected): bool => $collected->isChosen(), $held));
    }

    // None chosen gives the pack's cards their own pictures back
    public function testChoosingNoneClearsThePacksChoice(): void
    {
        $held = [new CollectedSkin('u1', $this->skin('toon'), 'GOB', 'quiz')];
        $held[0]->setChosen(true);

        $this->assertTrue($this->collector($held)->choose('u1', 'GOB', null));
        $this->assertFalse($held[0]->isChosen());
    }

    // A skin held for another pack only, or unpublished, is refused and nothing changes
    public function testRefusesASkinNotHeldForThatPackOrUnpublished(): void
    {
        $toon = $this->skin('toon');
        $draft = $this->skin('draft', false);
        $held = [new CollectedSkin('u1', $toon, 'GUE', 'quiz'), new CollectedSkin('u1', $draft, 'GOB', 'quiz')];

        $this->assertFalse($this->collector($held)->choose('u1', 'GOB', $toon));
        $this->assertFalse($this->collector($held)->choose('u1', 'GOB', $draft));
        $this->assertFalse($held[0]->isChosen() || $held[1]->isChosen());
    }

    // Only a published skin, in a pack it has pictures for and not held there, is given - or a paid one even unpublished or without pictures there since
    public function testGrantGivesOnlyAPublishedSkinInAPackItDresses(): void
    {
        $repository = $this->createStub(CollectedSkinRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(3))->method('persist');
        $collector = new SkinCollector($repository, $entityManager);

        $this->assertFalse($collector->grant('u1', $this->skin('toon'), 'ARC', 'quiz'));
        $this->assertFalse($collector->grant('u1', $this->skin('draft', false), 'GOB', 'quiz'));
        $this->assertTrue($collector->grant('u1', $this->skin('toon'), 'GOB', 'quiz'));
        $this->assertTrue($collector->grant('u1', $this->skin('draft', false), 'GOB', 'apple', paid: true));
        $this->assertTrue($collector->grant('u1', $this->skin('toon'), 'ARC', 'apple', paid: true));
    }

    // Already held for that pack, it is not given again
    public function testGrantRefusesASkinAlreadyHeldThere(): void
    {
        $toon = $this->skin('toon');
        $repository = $this->createStub(CollectedSkinRepository::class);
        $repository->method('findOneBy')->willReturn(new CollectedSkin('u1', $toon, 'GOB', 'quiz'));

        $this->assertFalse(new SkinCollector($repository, $this->createStub(EntityManagerInterface::class))->grant('u1', $toon, 'GOB', 'shop'));
    }

    // The same skin sent twice at once: the unique index refuses the second, which is "already held" and never a 500
    public function testGrantSentTwiceAtOnceIsAlreadyHeld(): void
    {
        $repository = $this->createStub(CollectedSkinRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->assertFalse(new SkinCollector($repository, $this->entityManagerRefusingADuplicate())->grant('u1', $this->skin('toon'), 'GOB', 'quiz'));
    }

    // A refund takes back what its source gave for that pack, and says so
    public function testRevokeRemovesOnlyWhatTheSourceGave(): void
    {
        $toon = $this->skin('toon');
        $repository = $this->createStub(CollectedSkinRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?CollectedSkin => 'apple' === $criteria['source'] && 'GOB' === $criteria['pack'] ? new CollectedSkin('u1', $toon, 'GOB', 'apple') : null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove');
        $collector = new SkinCollector($repository, $entityManager);

        $this->assertTrue($collector->revoke('u1', $toon, 'GOB', 'apple'));
        $this->assertFalse($collector->revoke('u1', $toon, 'GOB', 'google'));
    }

    // The skin each pack wears, by pack: one unchosen or unpublished since is left out
    public function testChosenIsThePacksSkinByPack(): void
    {
        [$toon, $draft] = [$this->skin('toon'), $this->skin('draft', false)];
        $held = [new CollectedSkin('u1', $toon, 'GOB', 'quiz'), new CollectedSkin('u1', $draft, 'GUE', 'quiz'), new CollectedSkin('u1', $toon, 'GUE', 'quiz')];
        $held[0]->setChosen(true);
        $held[1]->setChosen(true);

        $this->assertSame(['GOB' => $toon], $this->collector($held)->chosen('u1'));
    }

    /** @param list<CollectedSkin> $held */
    private function collector(array $held): SkinCollector
    {
        $repository = $this->createStub(CollectedSkinRepository::class);
        $repository->method('findByHolder')->willReturn($held);

        return new SkinCollector($repository, $this->createStub(EntityManagerInterface::class));
    }

    // A skin with a picture in GOB and in GUE
    private function skin(string $slug, bool $published = true): CardSkin
    {
        $skin = new CardSkin()->setSlug($slug)->setName($slug)->setPublished($published);
        foreach (['GOB 01' => 'GOB', 'GUE 01' => 'GUE'] as $reference => $pack) {
            $skin->getPictures()->add(new CardSkinPicture()->setSkin($skin)->setPack($pack)->setReference($reference));
        }

        return $skin;
    }
}
