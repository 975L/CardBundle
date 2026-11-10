<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Repository\CardSkinPictureRepository;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A skin's picture for one card - that hero in toon - named by the card's reference and filed under its pack, the site's own keys (see CardPackProviderInterface): the bundle never reads which pack a card belongs to, so the picture says it. A card with none keeps its own picture
#[ORM\Entity(repositoryClass: CardSkinPictureRepository::class)]
#[ORM\Table(name: 'card_skin_picture')]
#[ORM\UniqueConstraint(name: 'uniq_card_skin_picture', columns: ['skin_id', 'reference'])]
#[ORM\Index(name: 'idx_card_skin_picture_pack', columns: ['pack'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['skin', 'reference'])]
#[UniqueEntity(fields: ['slug'])]
#[Vich\Uploadable]
class CardSkinPicture implements VichMediaNamableInterface, VichMultiSizeImageInterface, \Stringable
{
    use BackImageTrait;

    // Where the uploads land, under UiBundle's own "block_media" mapping, as the backs' pictures
    public const string MEDIA_DIRECTORY = 'medias/cards/skins';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The foreign key's cascade kept as a safety net, CardSkin::$pictures removing them through Doctrine
    #[ORM\ManyToOne(inversedBy: 'pictures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?CardSkin $skin = null;

    // Letters, digits, dashes and underscores only, as a back picture's pack
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[A-Za-z0-9_-]+$/')]
    private ?string $pack = null;

    // The card's reference as the site names it ("GOB 01"), the same a CollectedCard holds
    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private ?string $reference = null;

    // Its skin's slug and its card's reference, never typed (see setSlugFromSkinAndReference()): what a content sync matches it by, and what its file is named after
    #[ORM\Column(length: 170, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $modification;

    public function __construct()
    {
        $this->modification = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSkin(): ?CardSkin
    {
        return $this->skin;
    }

    public function setSkin(?CardSkin $skin): static
    {
        $this->skin = $skin;
        $this->setSlugFromSkinAndReference();

        return $this;
    }

    public function getPack(): ?string
    {
        return $this->pack;
    }

    public function setPack(?string $pack): static
    {
        $this->pack = $pack;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        $this->setSlugFromSkinAndReference();

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    // Its slug written from its skin and its reference as soon as both are known, before validation so that UniqueEntity sees it, and again on persist as a safety net
    #[ORM\PrePersist]
    public function setSlugFromSkinAndReference(): void
    {
        $this->slug = $this->slugOf() ?? $this->slug;
    }

    // "toon-gob-01" for the toon picture of "GOB 01", null until both are known
    private function slugOf(): ?string
    {
        $skin = $this->skin?->getSlug();
        $reference = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $this->reference)), '-');

        return null === $skin || '' === $reference ? null : $skin . '-' . $reference;
    }

    public function getModification(): \DateTimeImmutable
    {
        return $this->modification;
    }

    // One file per skin and card, named after both
    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/' . ($this->slugOf() ?? uniqid());
    }

    // Every edit dates the row, the back office having no field for it
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->modification = new \DateTimeImmutable();
    }

    // What the back office names it by
    public function __toString(): string
    {
        return trim(($this->skin?->getName() ?? '') . ' - ' . ($this->reference ?? ''), ' -');
    }
}
