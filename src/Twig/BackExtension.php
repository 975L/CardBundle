<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Twig;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Repository\CardBackPictureRepository;
use c975L\CardBundle\Repository\CardBackRepository;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

// The standard back as the Back component draws it, for a site whose every card wears it: its name, tone and effect, and its picture for a pack when it has one - a site drawing its own picture lays it in the component's content instead
class BackExtension implements ResetInterface
{
    // Read once per request - or per message in a worker (see reset()) - a page calling it for every card it draws, false while not read yet
    private CardBack | false | null $standard = false;

    // The pictures of the standard back read so far, by pack
    /** @var array<string, ?string> */
    private array $images = [];

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
        if (false === $this->standard) {
            $this->standard = $this->cardBackRepository->findStandard();
        }
        $back = $this->standard;
        if (null === $back) {
            return null;
        }
        $image = null === $pack ? null : $this->imageOf($back, $pack);

        return [
            'slug' => $back->getSlug(),
            'name' => $back->getName(),
            'label' => $back->getName(),
            'image' => null === $image ? null : '/' . ltrim($image, '/'),
            'effect' => $back->getEffect(),
            'tone' => $back->getTone(),
        ];
    }

    // The standard back's picture for a pack, null when it has none there
    private function imageOf(CardBack $back, string $pack): ?string
    {
        $id = $back->getId();
        if (null === $id) {
            return null;
        }
        if (!\array_key_exists($pack, $this->images)) {
            $this->images[$pack] = $this->cardBackPictureRepository->findImagesOfPack($pack)[$id] ?? null;
        }

        return $this->images[$pack];
    }

    // Forgets what was read, so a long-running worker sees the back office's changes
    public function reset(): void
    {
        $this->standard = false;
        $this->images = [];
    }
}
