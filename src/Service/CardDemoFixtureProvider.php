<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CardBackPicture;
use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureMediaCopier;
use Symfony\Contracts\Translation\TranslatorInterface;

// The demo's card backs: three published variants, each in a tone and an effect, and their pictures over the site's first two packs, UiBundle's placeholder images. No catalogue and no collection: the cards are a site's own, and what players hold is what they did
class CardDemoFixtureProvider implements DemoFixtureProviderInterface
{
    // Slug, index of the pack its picture is for, name key, tone, effect
    private const array BACKS = [
        ['demo-dawn', 0, 'label.demo_back_dawn', BackTone::Dawn, BackEffect::Zoom],
        ['demo-storm', 0, 'label.demo_back_storm', BackTone::Storm, BackEffect::Lightning],
        ['demo-night', 1, 'label.demo_back_night', BackTone::Night, BackEffect::Stars],
    ];

    // The raw keys standing in for a site that lists no pack, typed in the back office as it then is
    private const array FALLBACK_PACKS = ['north', 'south'];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
        private readonly DemoFixtureMediaCopier $demoFixtureMediaCopier,
        private readonly CardCatalog $cardCatalog,
    ) {
    }

    // One back per BACKS row, its name in the site's locale, then its picture for one of the site's own packs, a copy of a placeholder, when the site declares one
    public function getDemoFixtures(): iterable
    {
        $packs = array_map(strval(...), array_keys($this->cardCatalog->packs()));

        foreach (self::BACKS as $position => [$slug, $index, $name, $tone, $effect]) {
            $back = new CardBack()
                ->setSlug($slug)
                ->setName($this->translator->trans($name, [], 'card'))
                ->setTone($tone)
                ->setEffect($effect)
                ->setPosition($position)
                ->setPublished(true);
            yield $back;

            // A site listing a single pack gets all three pictures in it
            $picture = $this->picture($slug);
            $file = null === $picture ? null : $this->demoFixtureMediaCopier->copy($picture);
            if (null !== $file) {
                yield new CardBackPicture()
                    ->setBack($back)
                    ->setPack($packs[$index] ?? $packs[0] ?? self::FALLBACK_PACKS[$index])
                    ->setImageFile($file);
            }
        }
    }

    // The back's own picture where the site declares one, keyed "card/<slug>" (see PlaceholderMediaProviderInterface), failing which one of the generic pool, none on a site declaring no pool
    private function picture(string $slug): ?string
    {
        $declared = $this->placeholderMediaRegistry->getImagesFor('card/' . $slug);
        if ([] !== $declared) {
            return $declared[0];
        }

        $pool = $this->placeholderMediaRegistry->getImages();
        if ([] === $pool) {
            return null;
        }

        // Read off the slug rather than off a counter, so the same back keeps the same picture whatever else is loaded beside it
        return $pool[crc32($slug) % \count($pool)];
    }
}
