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
use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureMediaCopier;
use Symfony\Contracts\Translation\TranslatorInterface;

// The demo's card backs: three published variants over the site's first two packs, each in a tone and an effect, illustrated by UiBundle's placeholder images. No catalogue and no collection: the cards are a site's own, and what players hold is what they did
class CardDemoFixtureProvider implements DemoFixtureProviderInterface
{
    // Slug, pack index, name key, tone, effect
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

    // One back per BACKS row, in the site's own packs so a player can choose it, numbered within its pack, its name in the site's locale and its picture a copy of a placeholder when the site declares one
    public function getDemoFixtures(): iterable
    {
        $packs = array_map(strval(...), array_keys($this->cardCatalog->packs()));
        $positions = [];

        foreach (self::BACKS as [$slug, $index, $name, $tone, $effect]) {
            // A site listing a single pack gets all three backs in it
            $pack = $packs[$index] ?? $packs[0] ?? self::FALLBACK_PACKS[$index];
            $positions[$pack] = ($positions[$pack] ?? -1) + 1;

            $back = new CardBack()
                ->setSlug($slug)
                ->setPack($pack)
                ->setName($this->translator->trans($name, [], 'card'))
                ->setTone($tone)
                ->setEffect($effect)
                ->setPosition($positions[$pack])
                ->setPublished(true);

            // A back with no picture of its own is drawn from its pack's, which the demo has none of: the placeholder stands in when there is one
            $picture = $this->picture($slug);
            $file = null === $picture ? null : $this->demoFixtureMediaCopier->copy($picture);
            if (null !== $file) {
                $back->setImageFile($file);
            }

            yield $back;
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
