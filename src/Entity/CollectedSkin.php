<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Repository\CollectedSkinRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// A skin a holder has for one pack, under the same key as their cards (see CollectedCard), and whether it is the one that pack's cards wear: one chosen at most per pack, none meaning the cards' own pictures
#[ORM\Entity(repositoryClass: CollectedSkinRepository::class)]
#[ORM\Table(name: 'card_collected_skin')]
#[ORM\UniqueConstraint(name: 'uniq_card_collected_skin', columns: ['holder', 'skin_id', 'pack'])]
#[ORM\Index(name: 'idx_card_collected_skin_holder', columns: ['holder'])]
class CollectedSkin
{
    use HoldingTrait;

    #[ORM\Column(options: ['default' => false])]
    private bool $chosen = false;

    public function __construct(
        #[ORM\Column(length: 40)]
        #[Assert\Length(max: 40)]
        private string $holder,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CardSkin $skin,
        // The site's own key of the pack it dresses, as a CardSkinPicture's
        #[ORM\Column(length: 40)]
        #[Assert\Length(max: 40)]
        private string $pack,
        // What gave it, in the site's own words, as for a card (see CollectedCard)
        #[ORM\Column(length: 20)]
        #[Assert\Length(max: 20)]
        private string $source,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getSkin(): CardSkin
    {
        return $this->skin;
    }

    public function getPack(): string
    {
        return $this->pack;
    }

    public function isChosen(): bool
    {
        return $this->chosen;
    }

    public function setChosen(bool $chosen): static
    {
        $this->chosen = $chosen;

        return $this;
    }
}
