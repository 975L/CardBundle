<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Repository\CardSkinRepository;
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

// A skin of the card faces - toon, sketch - held pack by pack, unlike a back held once for every pack: a holder wins or buys it for one pack and its cards wear it once chosen there. Each card's picture of it is a CardSkinPicture, the packs it dresses being those its pictures are for; a card with none keeps its own picture, and a finish still changes the frame and the numbers, never the skin
#[ORM\Entity(repositoryClass: CardSkinRepository::class)]
#[ORM\Table(name: 'card_skin')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
#[Vich\Uploadable]
class CardSkin implements VichMediaNamableInterface, VichMultiSizeImageInterface, \Stringable
{
    // Its image shown on its own, where no card draws it: the basket, the order emails, the invoice
    use BackImageTrait;

    // What a skin's store product id starts with (see getProductId())
    public const string PRODUCT_PREFIX = 'skin';

    // Where its thumbnail lands, beside the cards' pictures (see CardSkinPicture::MEDIA_DIRECTORY)
    public const string MEDIA_DIRECTORY = 'medias/cards/skins/thumbnails';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The skin's own name, the same for every pack: « Toon », « Croquis »
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private ?string $slug = null;

    // What it sells for in one pack, in cents and VAT included, the same for every pack, in the app stores and on the site; null for a skin only ever given
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $price = null;

    // The VAT rate its price includes, none for a seller under the VAT franchise
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private float $vat = 0.0;

    // Only a published skin is ever given or shown
    #[ORM\Column(options: ['default' => false])]
    private bool $published = false;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $position = null;

    // Removed with it one by one rather than by the foreign key alone, so that Vich deletes their files too
    /** @var Collection<int, CardSkinPicture> */
    #[ORM\OneToMany(targetEntity: CardSkinPicture::class, mappedBy: 'skin', cascade: ['remove'], orphanRemoval: true)]
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

    /** @return Collection<int, CardSkinPicture> */
    public function getPictures(): Collection
    {
        return $this->pictures;
    }

    // The packs it dresses, those its pictures are for, in the order they were given
    /** @return list<string> */
    public function getPacks(): array
    {
        $packs = [];
        foreach ($this->pictures as $picture) {
            $packs[] = (string) $picture->getPack();
        }

        return array_values(array_unique(array_filter($packs, static fn (string $pack): bool => '' !== $pack)));
    }

    // Whether it dresses that pack, a skin with no picture there having nothing to show in it
    public function dresses(string $pack): bool
    {
        return \in_array($pack, $this->getPacks(), true);
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

    // Whether it can be bought: published and priced
    public function isForSale(): bool
    {
        return $this->published && null !== $this->price && null !== $this->slug;
    }

    // The product the app stores sell it as in one pack, derived from its slug and the pack and never typed (see productIdOf()): "skin.toon.gob", null for a skin that is not for sale
    public function getProductId(string $pack): ?string
    {
        return null !== $this->price && null !== $this->slug ? self::productIdOf($this->slug, $pack) : null;
    }

    // A skin's slug and a pack turned into an id both stores accept (see InAppProduct::idFor()), a period between them since neither holds one: "skin.toon.gob", read back by CardSkinRepository::findByProductId()
    public static function productIdOf(string $slug, string $pack): string
    {
        return InAppProduct::idFor(self::PRODUCT_PREFIX, $slug) . '.' . str_replace('-', '_', strtolower($pack));
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    // One file per skin, named after it
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
