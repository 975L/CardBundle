<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Repository\CollectedBackRepository;
use Doctrine\ORM\Mapping as ORM;

// A card back a holder has, for every pack, under the same key as their cards (see CollectedCard), and whether it is the one their cards wear: one chosen at most, none meaning each pack's own picture. The back is a relation and not a reference, a back being this bundle's own row
#[ORM\Entity(repositoryClass: CollectedBackRepository::class)]
#[ORM\Table(name: 'card_collected_back')]
#[ORM\UniqueConstraint(name: 'uniq_card_collected_back', columns: ['holder', 'back_id'])]
#[ORM\Index(name: 'idx_card_collected_back_holder', columns: ['holder'])]
class CollectedBack
{
    use HoldingTrait;

    #[ORM\Column(options: ['default' => false])]
    private bool $chosen = false;

    public function __construct(
        #[ORM\Column(length: 40)]
        private string $holder,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CardBack $back,
        // What gave it, in the site's own words, as for a card (see CollectedCard)
        #[ORM\Column(length: 20)]
        private string $source,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getBack(): CardBack
    {
        return $this->back;
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
