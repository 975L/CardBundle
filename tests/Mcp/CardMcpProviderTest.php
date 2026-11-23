<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Mcp;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CardBackPicture;
use c975L\CardBundle\Mcp\CardMcpProvider;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Repository\CardSkinRepository;
use c975L\CardBundle\Service\BackStyles;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CardMcpProviderTest extends TestCase
{
    // The tools named under the "card" key, the writing ones flagged
    public function testTools(): void
    {
        $tools = iterator_to_array($this->provider()->getMcpTools(), false);

        $this->assertSame('card', $this->provider()->getMcpKey());
        $this->assertStringContainsString('at once', $this->provider()->getMcpInstructions());
        $this->assertSame(['card_back_list', 'card_back_get', 'card_back_create', 'card_back_edit', 'card_skin_list'], array_map(static fn ($tool): string => $tool->name, $tools));
        $this->assertSame([false, false, true, true, false], array_map(static fn ($tool): bool => $tool->writes, $tools));
        $this->assertStringContainsString('at once', $tools[3]->description);
    }

    // An unpublished back added, neither standard nor priced, with a slug kept unique, an empty name refused
    public function testCreateBack(): void
    {
        $repository = $this->createStub(CardBackRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?CardBack => 'item-1' === $criteria['slug'] ? new CardBack() : null);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->assertSame('item-1-2', $this->provider($repository, $entityManager)->createBack(' Item 1 ')['slug']);
        $this->assertInstanceOf(CardBack::class, $persisted[0]);
        $this->assertSame(['Item 1', false, false, null, CardBack::NO_STYLE], [$persisted[0]->getName(), $persisted[0]->isPublished(), $persisted[0]->isStandard(), $persisted[0]->getPrice(), $persisted[0]->getTone()]);

        $this->expectException(ToolCallException::class);
        $this->provider($repository, $entityManager)->createBack(' ');
    }

    // Only the fields given change, the slug kept
    public function testEditBack(): void
    {
        $back = new CardBack()->setName('item-1')->setSlug('item-1');
        $repository = $this->createStub(CardBackRepository::class);
        $repository->method('find')->willReturn($back);

        $this->provider($repository)->editBack(1, name: 'item-2');
        $this->assertSame(['item-2', 'item-1', CardBack::NO_STYLE], [$back->getName(), $back->getSlug(), $back->getTone()]);
    }

    // A tone the site does not offer is refused
    public function testUnknownStyleRefused(): void
    {
        $repository = $this->createStub(CardBackRepository::class);
        $repository->method('find')->willReturn(new CardBack()->setName('item-1'));

        $this->expectException(ToolCallException::class);
        $this->provider($repository)->editBack(1, tone: 'unknown');
    }

    // A back read with its style, its packs once each and the styles the site offers
    public function testGetBack(): void
    {
        $back = new CardBack()->setName('item-1')->setSlug('item-1');
        foreach (['pack-1', 'pack-1', 'pack-2'] as $pack) {
            $back->getPictures()->add(new CardBackPicture()->setBack($back)->setPack($pack));
        }
        $repository = $this->createStub(CardBackRepository::class);
        $repository->method('find')->willReturn($back);

        $read = $this->provider($repository)->getBack(1);
        $this->assertSame(['item-1', CardBack::NO_STYLE, ['pack-1', 'pack-2'], [CardBack::NO_STYLE]], [$read['name'], $read['tone'], $read['packs'], $read['tones']]);
    }

    // An unknown back answers as such
    public function testUnknownBack(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider()->getBack(1);
    }

    // An account that does not edit the backs and skins is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider(granted: false)->listSkins();
    }

    // An account that does not edit the backs and skins adds no back
    public function testCreateRefusedWithoutTheRole(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider(granted: false)->createBack('item-1');
    }

    // An account that does not edit the backs and skins writes no back
    public function testEditRefusedWithoutTheRole(): void
    {
        $back = new CardBack()->setName('item-1');
        $repository = $this->createStub(CardBackRepository::class);
        $repository->method('find')->willReturn($back);

        try {
            $this->provider($repository, granted: false)->editBack(1, name: 'item-2');
            $this->fail('The edit was not refused.');
        } catch (ToolCallException) {
            $this->assertSame('item-1', $back->getName());
        }
    }

    // The provider for an account editing the backs and skins or not
    private function provider(?CardBackRepository $repository = null, ?EntityManagerInterface $entityManager = null, bool $granted = true): CardMcpProvider
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        return new CardMcpProvider($repository ?? $this->createStub(CardBackRepository::class), $this->createStub(CardSkinRepository::class), new BackStyles([]), $entityManager ?? $this->createStub(EntityManagerInterface::class), $config, $security);
    }
}
