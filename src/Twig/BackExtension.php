<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Twig;

use c975L\CardBundle\Repository\CardBackPictureRepository;
use c975L\CardBundle\Repository\CardBackRepository;
use Twig\Attribute\AsTwigFunction;

// The standard back as the Back component draws it, for a site whose every card wears it: its name, tone and effect, and its picture for a pack when it has one - a site drawing its own picture lays it in the component's content instead
class BackExtension
{
    public function __construct(
        private readonly CardBackRepository $cardBackRepository,
        private readonly CardBackPictureRepository $cardBackPictureRepository,
    ) {
    }

    // Null while the site has no standard back published, the site then drawing its back as it did
    /** @return array{slug: ?string, name: ?string, label: ?string, image: ?string, effect: string, tone: string}|null */
    #[AsTwigFunction('card_back_standard')]
    public function standard(?string $pack = null): ?array
    {
        $back = $this->cardBackRepository->findStandard();
        if (null === $back) {
            return null;
        }
        $id = $back->getId();
        $image = null === $pack || null === $id ? null : $this->cardBackPictureRepository->findImagesOfPack($pack)[$id] ?? null;

        return [
            'slug' => $back->getSlug(),
            'name' => $back->getName(),
            'label' => $back->getName(),
            'image' => null === $image ? null : '/' . ltrim($image, '/'),
            'effect' => $back->getEffect(),
            'tone' => $back->getTone(),
        ];
    }
}
