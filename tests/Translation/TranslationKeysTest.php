<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Translation;

use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use c975L\CardBundle\Management\CardGuidedProjectProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
            preg_match_all("/'((?:label|help|description|narration|message)\.[a-z_]+[a-z])'/", (string) file_get_contents((string) $file), $matches);
            $keys = [...$keys, ...$matches[1]];
        }
        foreach ([...BackTone::cases(), ...BackEffect::cases()] as $case) {
            $keys[] = $case->label();
        }

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        foreach (['unsetAll', 'setController', 'setAction'] as $method) {
            $adminUrlGenerator->method($method)->willReturnSelf();
        }
        foreach (new CardGuidedProjectProvider($adminUrlGenerator, $this->createStub(ConfigServiceInterface::class))->getGuidedProjects() as $project) {
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
