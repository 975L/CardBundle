<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Entity\CollectedCard;
use c975L\CardBundle\Enum\CardFinish;
use c975L\CardBundle\Repository\CollectedCardRepository;
use c975L\CardBundle\Service\CardCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CardCollectorTest extends TestCase
{
    use DuplicateGiftTrait;

    // A finish not held is given and persisted, whatever is held below it: the ladder is the site's rule, never this one's
    public function testGivesAFinishNotHeldEvenWithoutTheOneBelow(): void
    {
        $repository = $this->createStub(CollectedCardRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(CollectedCard::class));
        $entityManager->expects($this->once())->method('flush');

        $collected = new CardCollector($repository, $entityManager)->grant('u1', 'GUE 02', CardFinish::Magic, 'quiz');

        $this->assertInstanceOf(CollectedCard::class, $collected);
        $this->assertSame('GUE 02', $collected->getReference());
        $this->assertSame(CardFinish::Magic, $collected->getFinish());
        $this->assertSame('quiz', $collected->getSource());
    }

    // The same finish twice is refused, another finish of the same card is not: the one row of the unique index is all that is read
    public function testRefusesAFinishAlreadyHeld(): void
    {
        $held = new CollectedCard('u1', 'GUE 02', CardFinish::Normal, 'quiz');
        $repository = $this->createStub(CollectedCardRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?CollectedCard => CardFinish::Normal === $criteria['finish'] && 'GUE 02' === $criteria['reference'] && 'u1' === $criteria['holder'] ? $held : null);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $collector = new CardCollector($repository, $entityManager);

        $this->assertNull($collector->grant('u1', 'GUE 02', CardFinish::Normal, 'quiz'));
        $this->assertNotNull($collector->grant('u1', 'GUE 02', CardFinish::Shiny, 'quiz'));
    }

    // The same card sent twice at once passes the check twice: the unique index refuses the second, which is "already held" and never a 500
    public function testAGiftSentTwiceAtOnceIsAlreadyHeld(): void
    {
        $repository = $this->createStub(CollectedCardRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->assertNull(new CardCollector($repository, $this->entityManagerRefusingADuplicate())->grant('u1', 'GUE 02', CardFinish::Normal, 'quiz'));
    }
}
