<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Management;

use c975L\CardBundle\Contract\CardCatalogInterface;
use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Entity\CollectedBack;
use c975L\CardBundle\Entity\CollectedCard;
use c975L\CardBundle\Entity\CollectedSkin;
use c975L\CardBundle\Enum\CardFinish;
use c975L\CardBundle\Management\AccountDataProvider;
use c975L\CardBundle\Repository\CollectedBackRepository;
use c975L\CardBundle\Repository\CollectedCardRepository;
use c975L\CardBundle\Repository\CollectedSkinRepository;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class AccountDataProviderTest extends TestCase
{
    // The cards named by the site's catalogue where it knows them, by their reference otherwise, the backs and the skins beside them, a skin's pack by its key when the site lists none
    public function testExportsTheCardsTheBacksAndTheSkinsOfTheAccount(): void
    {
        $catalog = $this->createStub(CardCatalogInterface::class);
        $catalog->method('getCardName')->willReturnCallback(static fn (string $reference): ?string => 'GUE 02' === $reference ? 'Athelleen' : null);
        $back = new CollectedBack('u3', new CardBack()->setName('À l\'aube'), 'quiz');
        $back->setChosen(true);

        $data = $this->provider(
            [new CollectedCard('u3', 'GUE 02', CardFinish::Shiny, 'quiz'), new CollectedCard('u3', 'XYZ 99', CardFinish::Normal, 'app')],
            [$back],
            [$catalog],
            [new CollectedSkin('u3', new CardSkin()->setName('Toon'), 'GOB', 'shop')],
        )->getAccountData($this->user(3));

        $this->assertSame(['Athelleen', 'XYZ 99'], array_column($data['cards'], 'name'));
        $this->assertSame(['shiny', 'normal'], array_column($data['cards'], 'finish'));
        $this->assertSame('À l\'aube', $data['backs'][0]['back']);
        $this->assertTrue($data['backs'][0]['chosen']);
        $this->assertSame(['skin' => 'Toon', 'pack' => 'GOB', 'chosen' => false, 'source' => 'shop'], array_intersect_key($data['skins'][0], array_flip(['skin', 'pack', 'chosen', 'source'])));
    }

    // An account that holds nothing adds nothing to its export
    public function testAnEmptyCollectionExportsNothing(): void
    {
        $this->assertSame([], $this->provider([], [], [], [])->getAccountData($this->user(3)));
    }

    /**
     * @param list<CollectedCard>        $cards
     * @param list<CollectedBack>        $backs
     * @param list<CardCatalogInterface> $catalogs
     * @param list<CollectedSkin>        $skins
     */
    private function provider(array $cards, array $backs, array $catalogs, array $skins): AccountDataProvider
    {
        $cardRepository = $this->createStub(CollectedCardRepository::class);
        $cardRepository->method('findByHolder')->willReturn($cards);
        $backRepository = $this->createStub(CollectedBackRepository::class);
        $backRepository->method('findByHolder')->willReturn($backs);

        $skinRepository = $this->createStub(CollectedSkinRepository::class);
        $skinRepository->method('findByHolder')->willReturn($skins);

        return new AccountDataProvider($cardRepository, $backRepository, $skinRepository, new CardCatalog($catalogs, [], $this->createStub(ConfigServiceInterface::class)));
    }

    private function user(int $id): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }
}
