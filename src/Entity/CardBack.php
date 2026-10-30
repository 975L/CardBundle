<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Enum\BackEffect;
use c975L\CardBundle\Enum\BackTone;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\PaymentBundle\Contract\InAppProduct;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A variant of a pack's card back - a castle at dawn, at sunset, under the storm - worn by the cards of that pack once its holder chooses it. Only the variants are stored: with none chosen, a card's back is whatever the site draws for its pack. The pack is the site's own key (see CardPackProviderInterface), and how a back is had is none of its columns: a reward, a purchase or a code each give it through BackCollector
#[ORM\Entity(repositoryClass: CardBackRepository::class)]
#[ORM\Table(name: 'card_back')]
#[ORM\Index(name: 'idx_card_back_pack', columns: ['pack'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
#[Vich\Uploadable]
class CardBack implements VichMediaNamableInterface, VichMultiSizeImageInterface, \Stringable
{
    // Where the uploads land, under UiBundle's own "block_media" mapping rather than a mapping of this bundle's
    public const string MEDIA_DIRECTORY = 'medias/cards/backs';

    // What a back's store product id starts with (see getProductId())
    public const string PRODUCT_PREFIX = 'back';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The pack whose cards wear it - a back is never moved to another pack
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private ?string $pack = null;

    // The variant's own name, the pack's being on the back already: « À l'aube », « Coucher de soleil »
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private ?string $slug = null;

    // Its own illustration, none drawing its pack's picture in its tone
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    // Never persisted: Vich converts the upload to webp and UiBundle cuts its "-thumb"/"-highres" siblings
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 10, enumType: BackEffect::class, options: ['default' => 'none'])]
    private BackEffect $effect = BackEffect::None;

    #[ORM\Column(length: 10, enumType: BackTone::class, options: ['default' => 'none'])]
    private BackTone $tone = BackTone::None;

    // What it sells for, in cents and VAT included, the same in the app stores and on the site; null for a back that is not for sale, only ever given
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $price = null;

    // The VAT rate its price includes, none for a seller under the VAT franchise
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private float $vat = 0.0;

    // Only a published back is ever given or shown
    #[ORM\Column(options: ['default' => false])]
    private bool $published = false;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $position = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creation;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $modification;

    public function __construct()
    {
        $this->creation = new \DateTimeImmutable();
        $this->modification = $this->creation;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    // Stamping the modification date is what makes Doctrine see a change at all, $imageFile being no column
    public function setImageFile(?File $imageFile): static
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            $this->modification = new \DateTimeImmutable();
        }

        return $this;
    }

    // One file per variant, named after its slug
    public function getVichMediaPath(): string
    {
        return self::MEDIA_DIRECTORY . '/' . ($this->slug ?? uniqid());
    }

    // A back is drawn no wider than a card
    public function getImageWidth(): int
    {
        return 800;
    }

    public function getThumbnailSize(): int
    {
        return 500;
    }

    public function getHighresWidth(): int
    {
        return 1200;
    }

    public function getEffect(): BackEffect
    {
        return $this->effect;
    }

    public function setEffect(BackEffect $effect): static
    {
        $this->effect = $effect;

        return $this;
    }

    public function getTone(): BackTone
    {
        return $this->tone;
    }

    public function setTone(BackTone $tone): static
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

    // Whether it can be bought: published and priced
    public function isForSale(): bool
    {
        return $this->published && null !== $this->price && null !== $this->slug;
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
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

    // A tone and an effect drawn on the same layer of the picture, one would hide the other
    #[Assert\Callback]
    public function validateLayers(ExecutionContextInterface $context): void
    {
        if ($this->tone->drawsOverPicture() && $this->effect->drawsOverPicture()) {
            $context->buildViolation('message.back_layers_clash')
                ->setTranslationDomain('card')
                ->atPath('effect')
                ->addViolation();
        }
    }

    // What the back office and a reward's message name it by
    public function __toString(): string
    {
        return trim(($this->pack ?? '') . ' - ' . ($this->name ?? ''), ' -');
    }
}
