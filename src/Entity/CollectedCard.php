<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Enum\CardFinish;
use c975L\CardBundle\Repository\CollectedCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// One finish of one card in a holder's collection. What is collected is the holding, not the card: the catalogue is the site's own (see CardCatalogInterface), named here by its reference rather than tied by a foreign key, so a site whose cards are eight kinds of fiche needs one column and not eight. Held under a key rather than a relation to the account - "u<id>" for an account, the token the device made up otherwise - so a device collects before anyone signs up (see CardHolder)
#[ORM\Entity(repositoryClass: CollectedCardRepository::class)]
#[ORM\Table(name: 'card_collected')]
#[ORM\UniqueConstraint(name: 'uniq_card_collected', columns: ['holder', 'reference', 'finish'])]
#[ORM\Index(name: 'idx_card_collected_holder', columns: ['holder'])]
class CollectedCard
{
    use HoldingTrait;

    public function __construct(
        #[ORM\Column(length: 40)]
        #[Assert\Length(max: 40)]
        private string $holder,
        #[ORM\Column(length: 80)]
        #[Assert\Length(max: 80)]
        private string $reference,
        #[ORM\Column(length: 10, enumType: CardFinish::class)]
        private CardFinish $finish,
        // What gave it, in the site's own words ("quiz", "draw", "app"), so a collection can tell its ways in apart
        #[ORM\Column(length: 20)]
        #[Assert\Length(max: 20)]
        private string $source,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getFinish(): CardFinish
    {
        return $this->finish;
    }
}
