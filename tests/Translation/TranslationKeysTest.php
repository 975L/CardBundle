<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Translation;

use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Management\CardGuidedProjectProvider;
use c975L\CardBundle\Service\BackThumbnailMaker;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Every key the code names is shipped in the three languages - read off the code itself, the enums and the guided project building theirs from a value, which a search of the sources for literal keys would never see
class TranslationKeysTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        foreach (['fr', 'en', 'es'] as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testEveryKeyIsShipped(string $locale): void
    {
        $catalogue = self::catalogue('card', $locale);
        $narration = self::catalogue('card_narration', $locale);

        foreach ($this->usedKeys() as $key) {
            $this->assertArrayHasKey($key, str_starts_with($key, 'narration.') ? $narration : $catalogue, sprintf('"%s" is missing from the %s catalogue', $key, $locale));
        }
    }

    // The other way round: a key nobody names any more is dead weight in a catalogue written by hand
    public function testNoKeyIsShippedWithoutBeingNamed(): void
    {
        $used = array_flip($this->usedKeys());

        foreach ([...array_keys(self::catalogue('card', 'en')), ...array_keys(self::catalogue('card_narration', 'en'))] as $key) {
            $this->assertArrayHasKey($key, $used, sprintf('"%s" is shipped but named nowhere', $key));
        }
    }

    /** @return list<string> */
    private function usedKeys(): array
    {
        $keys = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/src', \FilesystemIterator::SKIP_DOTS)) as $file) {
            preg_match_all("/'((?:label|help|description|narration|message|flash)\.[a-z_]+[a-z])'/", (string) file_get_contents((string) $file), $matches);
            $keys = [...$keys, ...$matches[1]];
        }
        // The templates name theirs with the domain beside them, other bundles' keys passing through too
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/templates', \FilesystemIterator::SKIP_DOTS)) as $file) {
            preg_match_all("/'(label\.[a-z_]+[a-z])'\|trans\((?:\{[^}]*\})?, 'card'\)/", (string) file_get_contents((string) $file), $matches);
            $keys = [...$keys, ...$matches[1]];
        }

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        foreach (['unsetAll', 'setController', 'setAction'] as $method) {
            $adminUrlGenerator->method($method)->willReturnSelf();
        }
        // The export and thumbnails projects only show where their tiles can run
        $cardExporter = $this->createStub(CardExporter::class);
        $cardExporter->method('isAvailable')->willReturn(true);
        $backThumbnailMaker = $this->createStub(BackThumbnailMaker::class);
        $backThumbnailMaker->method('isAvailable')->willReturn(true);
        foreach (new CardGuidedProjectProvider($adminUrlGenerator, $this->createStub(ConfigServiceInterface::class), $this->createStub(UrlGeneratorInterface::class), $cardExporter, $backThumbnailMaker)->getGuidedProjects() as $project) {
            $keys[] = $project['label'];
            $keys[] = $project['description'];
            foreach ($project['steps'] as $step) {
                $keys = [...$keys, $step['label'], $step['description'], $step['narration']];
            }
        }

        return array_values(array_unique($keys));
    }

    /** @return array<string, string> */
    private static function catalogue(string $domain, string $locale): array
    {
        $xml = simplexml_load_file(\dirname(__DIR__, 2) . '/translations/' . $domain . '.' . $locale . '.xlf');
        $xml->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        $units = [];
        foreach ($xml->xpath('//x:trans-unit') ?: [] as $unit) {
            $units[(string) $unit->source] = (string) $unit->target;
        }

        return $units;
    }
}
