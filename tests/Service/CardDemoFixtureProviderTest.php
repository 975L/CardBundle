<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Contract\CardPackProviderInterface;
use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Service\CardCatalog;
use c975L\CardBundle\Service\CardDemoFixtureProvider;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureMediaCopier;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

class CardDemoFixtureProviderTest extends TestCase
{
    // Three published backs over two packs, each slug unique, none of them drawing a tone and an effect on the same layer
    public function testSeedsPublishedBacksOverTwoPacks(): void
    {
        $backs = $this->backs();

        $this->assertCount(3, $backs);
        $this->assertContainsOnlyInstancesOf(CardBack::class, $backs);
        $this->assertSame(['north', 'north', 'south'], $this->packsOf($backs));
        $this->assertSame([0, 1, 0], array_map(static fn (CardBack $back): ?int => $back->getPosition(), $backs));
        $this->assertCount(3, array_unique(array_map(static fn (CardBack $back): ?string => $back->getSlug(), $backs)));
        foreach ($backs as $back) {
            $this->assertTrue($back->isPublished());
            $this->assertFalse($back->getTone()->drawsOverPicture() && $back->getEffect()->drawsOverPicture());
            $this->assertNull($back->getImageFile());
        }
    }

    // A site listing its packs gets the backs in its own, a player being able to choose only those
    public function testUsesTheSitePacks(): void
    {
        $this->assertSame(['castle', 'castle', 'forest'], $this->packsOf($this->backs(['castle' => 'Castle', 'forest' => 'Forest'])));
        $this->assertSame(['castle', 'castle', 'castle'], $this->packsOf($this->backs(['castle' => 'Castle'])));
    }

    // Each back carries a copy of a placeholder, its own when the site declares one, the placeholder itself staying on disk
    public function testIllustratesEachBackWithACopyOfAPlaceholder(): void
    {
        $projectDir = sys_get_temp_dir() . '/c975l-card-test-' . uniqid();
        mkdir($projectDir . '/public', 0o777, true);
        file_put_contents($projectDir . '/public/pool.jpg', 'pool');
        file_put_contents($projectDir . '/public/night.jpg', 'night');

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn(['pool.jpg']);
        $registry->method('getImagesFor')->willReturnCallback(static fn (string $key): array => 'card/demo-night' === $key ? ['night.jpg'] : []);

        $backs = $this->backs([], $registry, $projectDir);

        foreach ($backs as $back) {
            $this->assertInstanceOf(ReplacingFile::class, $back->getImageFile());
            $this->assertStringStartsNotWith($projectDir, $back->getImageFile()->getPathname());
        }
        $this->assertSame(['pool', 'pool', 'night'], array_map(static fn (CardBack $back): string => file_get_contents($back->getImageFile()->getPathname()), $backs));
        $this->assertFileExists($projectDir . '/public/pool.jpg');
        $this->assertFileExists($projectDir . '/public/night.jpg');
    }

    /**
     * @param array<string, string> $packs
     *
     * @return list<CardBack>
     */
    private function backs(array $packs = [], ?PlaceholderMediaRegistry $registry = null, string $projectDir = ''): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $packProviders = [];
        if ([] !== $packs) {
            $packProvider = $this->createStub(CardPackProviderInterface::class);
            $packProvider->method('getPacks')->willReturn($packs);
            $packProviders[] = $packProvider;
        }

        $provider = new CardDemoFixtureProvider(
            $translator,
            $registry ?? new PlaceholderMediaRegistry(),
            new DemoFixtureMediaCopier($projectDir),
            new CardCatalog([], $packProviders),
        );

        return iterator_to_array($provider->getDemoFixtures(), false);
    }

    /**
     * @param list<CardBack> $backs
     *
     * @return list<?string>
     */
    private function packsOf(array $backs): array
    {
        return array_map(static fn (CardBack $back): ?string => $back->getPack(), $backs);
    }
}
