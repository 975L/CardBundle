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
use c975L\CardBundle\Entity\CardBackPicture;
use c975L\CardBundle\Service\CardCatalog;
use c975L\CardBundle\Service\CardDemoFixtureProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureMediaCopier;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

class CardDemoFixtureProviderTest extends TestCase
{
    // Three published backs in order, each slug unique, none of them drawing a tone and an effect on the same layer, and no picture where the site declares no placeholder
    public function testSeedsThreePublishedBacks(): void
    {
        $fixtures = $this->fixtures();

        $this->assertCount(3, $fixtures);
        $this->assertContainsOnlyInstancesOf(CardBack::class, $fixtures);
        $this->assertSame([0, 1, 2], array_map(static fn (CardBack $back): ?int => $back->getPosition(), $fixtures));
        $this->assertCount(3, array_unique(array_map(static fn (CardBack $back): ?string => $back->getSlug(), $fixtures)));
        foreach ($fixtures as $back) {
            $this->assertTrue($back->isPublished());
            $this->assertFalse($back->getTone()->drawsOverPicture() && $back->getEffect()->drawsOverPicture());
        }
    }

    // Each back's picture follows it, a copy of a placeholder - its own when the site declares one - in the site's own packs, the placeholder itself staying on disk
    public function testIllustratesEachBackWithACopyOfAPlaceholderInTheSitePacks(): void
    {
        $projectDir = sys_get_temp_dir() . '/c975l-card-test-' . uniqid();
        mkdir($projectDir . '/public', 0o777, true);
        file_put_contents($projectDir . '/public/pool.jpg', 'pool');
        file_put_contents($projectDir . '/public/night.jpg', 'night');

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn(['pool.jpg']);
        $registry->method('getImagesFor')->willReturnCallback(static fn (string $key): array => 'card/demo-night' === $key ? ['night.jpg'] : []);

        $fixtures = $this->fixtures(['castle' => 'Castle', 'forest' => 'Forest'], $registry, $projectDir);
        $pictures = array_values(array_filter($fixtures, static fn (object $fixture): bool => $fixture instanceof CardBackPicture));

        $this->assertCount(6, $fixtures);
        $this->assertSame($fixtures[0], $pictures[0]->getBack());
        $this->assertSame(['castle', 'castle', 'forest'], array_map(static fn (CardBackPicture $picture): ?string => $picture->getPack(), $pictures));
        foreach ($pictures as $picture) {
            $this->assertInstanceOf(ReplacingFile::class, $picture->getImageFile());
            $this->assertStringStartsNotWith($projectDir, $picture->getImageFile()->getPathname());
        }
        $this->assertSame(['pool', 'pool', 'night'], array_map(static fn (CardBackPicture $picture): string => file_get_contents($picture->getImageFile()->getPathname()), $pictures));
        $this->assertFileExists($projectDir . '/public/pool.jpg');
        $this->assertFileExists($projectDir . '/public/night.jpg');
    }

    // A site listing no pack gets the raw keys, typed in the back office as it then is
    public function testFallsBackToRawPackKeys(): void
    {
        $projectDir = sys_get_temp_dir() . '/c975l-card-test-' . uniqid();
        mkdir($projectDir . '/public', 0o777, true);
        file_put_contents($projectDir . '/public/pool.jpg', 'pool');
        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getImages')->willReturn(['pool.jpg']);
        $registry->method('getImagesFor')->willReturn([]);

        $pictures = array_values(array_filter($this->fixtures([], $registry, $projectDir), static fn (object $fixture): bool => $fixture instanceof CardBackPicture));

        $this->assertSame(['north', 'north', 'south'], array_map(static fn (CardBackPicture $picture): ?string => $picture->getPack(), $pictures));
    }

    /**
     * @param array<string, string> $packs
     *
     * @return list<CardBack|CardBackPicture>
     */
    private function fixtures(array $packs = [], ?PlaceholderMediaRegistry $registry = null, string $projectDir = ''): array
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
            new CardCatalog([], $packProviders, $this->createStub(ConfigServiceInterface::class)),
        );

        return iterator_to_array($provider->getDemoFixtures(), false);
    }
}
