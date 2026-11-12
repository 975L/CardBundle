<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Repository\CardBackRepository;
use c975L\PaymentBundle\Contract\InAppProduct;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A variant of the card backs - at dawn, at sunset, under the storm - held once for every pack and worn by all the holder's cards once chosen. Only the variants are stored: with none chosen, a card's back is whatever the site draws for its pack. Each pack's picture of it is a CardBackPicture, and how a back is had is none of its columns: a reward, a purchase or a code each give it through BackCollector
#[ORM\Entity(repositoryClass: CardBackRepository::class)]
#[ORM\Table(name: 'card_back')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
#[Vich\Uploadable]
class CardBack implements VichMediaNamableInterface, VichMultiSizeImageInterface, \Stringable
{
    // Its image shown on its own, where no pack draws it: the basket, the order emails, the invoice
    use BackImageTrait;

    // Neither tone nor effect, the only value the bundle itself knows
    public const string NO_STYLE = 'none';

    // What a back's store product id starts with (see getProductId())
    public const string PRODUCT_PREFIX = 'back';

    // Where its thumbnail lands, beside the packs' pictures (see CardBackPicture::MEDIA_DIRECTORY)
    public const string MEDIA_DIRECTORY = 'medias/cards/backs/thumbnails';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The variant's own name, the same for every pack: « À l'aube », « Coucher de soleil »
    #[ORM\Column(length: 60)]
    #[Assert\Length(max: 60)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\Length(max: 80)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private ?string $slug = null;

    // How it moves and the light on its picture, values the site offers and draws (see BackStyleProviderInterface)
    #[ORM\Column(length: 10, options: ['default' => 'none'])]
    #[Assert\Length(max: 10)]
    private string $effect = self::NO_STYLE;

    #[ORM\Column(length: 10, options: ['default' => 'none'])]
    #[Assert\Length(max: 10)]
    private string $tone = self::NO_STYLE;

    // What it sells for, in cents and VAT included, the same in the app stores and on the site; null for a back that is not for sale, only ever given
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $price = null;

    // The VAT rate its price includes, none for a seller under the VAT franchise
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private float $vat = 0.0;

    // The back every card wears until its holder chooses another, one per site: worn by all without being given, never sold, drawn on each pack's own picture unless it has one for that pack
    #[ORM\Column(options: ['default' => false])]
    private bool $standard = false;

    // Only a published back is ever given or shown
    #[ORM\Column(options: ['default' => false])]
    private bool $published = false;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $position = null;

    // Removed with it one by one rather than by the foreign key alone, so that Vich deletes their files too
    /** @var Collection<int, CardBackPicture> */
    #[ORM\OneToMany(targetEntity: CardBackPicture::class, mappedBy: 'back', cascade: ['remove'], orphanRemoval: true)]
    private Collection $pictures;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creation;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $modification;

    public function __construct()
    {
        $this->pictures = new ArrayCollection();
        $this->creation = new \DateTimeImmutable();
        $this->modification = $this->creation;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

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

    /** @return Collection<int, CardBackPicture> */
    public function getPictures(): Collection
    {
        return $this->pictures;
    }

    public function getEffect(): string
    {
        return $this->effect;
    }

    public function setEffect(string $effect): static
    {
        $this->effect = $effect;

        return $this;
    }

    public function getTone(): string
    {
        return $this->tone;
    }

    public function setTone(string $tone): static
    {
        $this->tone = $tone;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getVat(): float
    {
        return $this->vat;
    }

    public function setVat(float $vat): static
    {
        $this->vat = $vat;

        return $this;
    }

    // Whether it can be bought: published and priced, the standard one never
    public function isForSale(): bool
    {
        return !$this->standard && $this->published && null !== $this->price && null !== $this->slug;
    }

    // The product the app stores sell it as, derived from its slug and never typed (see InAppProduct::idFor()), null for a back that is not for sale
    public function getProductId(): ?string
    {
        return null !== $this->price && null !== $this->slug ? InAppProduct::idFor(self::PRODUCT_PREFIX, $this->slug) : null;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function isStandard(): bool
    {
        return $this->standard;
    }

    public function setStandard(bool $standard): static
    {
        $this->standard = $standard;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    // One file per back, named after it
    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/' . ($this->slug ?? uniqid());
    }

    public function getCreation(): \DateTimeImmutable
    {
        return $this->creation;
    }

    public function getModification(): \DateTimeImmutable
    {
        return $this->modification;
    }

    // Every edit dates the row, the back office having no field for it
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->modification = new \DateTimeImmutable();
    }

    // What the back office and a reward's message name it by
    public function __toString(): string
    {
        return (string) $this->name;
    }
}
