<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CollectedBack;
use c975L\CardBundle\Repository\CollectedBackRepository;
use c975L\CardBundle\Service\BackCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BackCollectorTest extends TestCase
{
    use DuplicateGiftTrait;

    // Choosing a back marks it and clears every other of its pack, leaving the other packs' choices alone
    public function testChoosingABackClearsItsPackOnly(): void
    {
        [$dawn, $storm, $other] = [$this->back('hur', 'dawn'), $this->back('hur', 'storm'), $this->back('gob', 'night')];
        $held = [new CollectedBack('u1', $dawn, 'quiz'), new CollectedBack('u1', $storm, 'quiz'), new CollectedBack('u1', $other, 'quiz')];
        $held[1]->setChosen(true);
        $held[2]->setChosen(true);

        $this->assertTrue($this->collector($held)->choose('u1', 'hur', $dawn));

        $this->assertSame([true, false, true], array_map(static fn (CollectedBack $collected): bool => $collected->isChosen(), $held));
    }

    // None chosen goes back to the pack's own picture
    public function testChoosingNoneClearsThePack(): void
    {
        $held = [new CollectedBack('u1', $this->back('hur', 'dawn'), 'quiz')];
        $held[0]->setChosen(true);

        $this->assertTrue($this->collector($held)->choose('u1', 'hur', null));
        $this->assertFalse($held[0]->isChosen());
    }

    // A back of another pack, or one not held, is refused and nothing changes
    public function testRefusesABackOfAnotherPackOrNotHeld(): void
    {
        $dawn = $this->back('hur', 'dawn');
        $held = [new CollectedBack('u1', $dawn, 'quiz')];
        $collector = $this->collector($held);

        $this->assertFalse($collector->choose('u1', 'gob', $dawn));
        $this->assertFalse($collector->choose('u1', 'hur', $this->back('hur', 'storm')));
        $this->assertFalse($held[0]->isChosen());
    }

    // An unpublished back is withdrawn: held, it still cannot be chosen
    public function testRefusesAnUnpublishedBack(): void
    {
        $draft = $this->back('hur', 'draft', false);
        $held = [new CollectedBack('u1', $draft, 'quiz')];

        $this->assertFalse($this->collector($held)->choose('u1', 'hur', $draft));
        $this->assertFalse($held[0]->isChosen());
    }

    // Only a published back not already held is given - or a paid one even unpublished since, a purchase being owed
    public function testGrantGivesOnlyAPublishedBackNotHeld(): void
    {
        $repository = $this->createStub(CollectedBackRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))->method('persist');
        $collector = new BackCollector($repository, $entityManager);

        $this->assertFalse($collector->grant('u1', $this->back('hur', 'draft', false), 'quiz'));
        $this->assertTrue($collector->grant('u1', $this->back('hur', 'dawn'), 'quiz'));
        $this->assertTrue($collector->grant('u1', $this->back('hur', 'draft', false), 'apple', paid: true));
    }

    // The same back sent twice at once passes the check twice: the unique index refuses the second, which is "already held" and never a 500
    public function testGrantSentTwiceAtOnceIsAlreadyHeld(): void
    {
        $repository = $this->createStub(CollectedBackRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->assertFalse(new BackCollector($repository, $this->entityManagerRefusingADuplicate())->grant('u1', $this->back('hur', 'dawn'), 'quiz'));
    }

    // A refund takes back what its source gave, and says so; nothing held from that source, nothing is removed
    public function testRevokeRemovesOnlyWhatTheSourceGave(): void
    {
        $back = $this->back('hur', 'dawn');
        $repository = $this->createStub(CollectedBackRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?CollectedBack => 'apple' === $criteria['source'] ? new CollectedBack('u1', $back, 'apple') : null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove');
        $collector = new BackCollector($repository, $entityManager);

        $this->assertTrue($collector->revoke('u1', $back, 'apple'));
        $this->assertFalse($collector->revoke('u1', $back, 'google'));
    }

    // The chosen backs by pack, a pack with none chosen absent
    public function testChosenListsOneBackPerPack(): void
    {
        $dawn = $this->back('hur', 'dawn');
        $held = [new CollectedBack('u1', $dawn, 'quiz'), new CollectedBack('u1', $this->back('gob', 'night'), 'quiz')];
        $held[0]->setChosen(true);

        $this->assertSame(['hur' => $dawn], $this->collector($held)->chosen('u1'));
    }

    // An unpublished back is no longer worn, but stays chosen: republished, it is worn again
    public function testChosenLeavesOutAnUnpublishedBack(): void
    {
        $dawn = $this->back('hur', 'dawn', false);
        $held = [new CollectedBack('u1', $dawn, 'quiz')];
        $held[0]->setChosen(true);
        $collector = $this->collector($held);

        $this->assertSame([], $collector->chosen('u1'));
        $this->assertTrue($held[0]->isChosen());

        $dawn->setPublished(true);
        $this->assertSame(['hur' => $dawn], $collector->chosen('u1'));
    }

    /** @param list<CollectedBack> $held */
    private function collector(array $held): BackCollector
    {
        $repository = $this->createStub(CollectedBackRepository::class);
        $repository->method('findByHolder')->willReturn($held);

        return new BackCollector($repository, $this->createStub(EntityManagerInterface::class));
    }

    private function back(string $pack, string $slug, bool $published = true): CardBack
    {
        return new CardBack()->setPack($pack)->setSlug($slug)->setName($slug)->setPublished($published);
    }
}
