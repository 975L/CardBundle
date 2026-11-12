<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use c975L\CardBundle\Repository\CardBackPictureRepository;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A back's picture for one pack - a castle at dawn is that castle's own - the pack being the site's own key (see CardPackProviderInterface). A pack with none draws its own picture in the back's tone
#[ORM\Entity(repositoryClass: CardBackPictureRepository::class)]
#[ORM\Table(name: 'card_back_picture')]
#[ORM\UniqueConstraint(name: 'uniq_card_back_picture', columns: ['back_id', 'pack'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['back', 'pack'])]
#[UniqueEntity(fields: ['slug'])]
#[Vich\Uploadable]
class CardBackPicture implements VichMediaNamableInterface, VichMultiSizeImageInterface, \Stringable
{
    use BackImageTrait;

    // Where the uploads land, under UiBundle's own "block_media" mapping rather than a mapping of this bundle's
    public const string MEDIA_DIRECTORY = 'medias/cards/backs';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The foreign key's cascade kept as a safety net, CardBack::$pictures removing them through Doctrine
    #[ORM\ManyToOne(inversedBy: 'pictures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?CardBack $back = null;

    // Letters, digits, dashes and underscores only, as it goes into the slug and the file's path
    #[ORM\Column(length: 40)]
    #[Assert\Length(max: 40)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[A-Za-z0-9_-]+$/')]
    private ?string $pack = null;

    // Its back's slug and its pack, never typed (see setSlugFromBackAndPack()): what a content sync between two sites matches it by, and what its file is named after
    #[ORM\Column(length: 130, unique: true)]
    #[Assert\Length(max: 130)]
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

    public function getBack(): ?CardBack
    {
        return $this->back;
    }

    public function setBack(?CardBack $back): static
    {
        $this->back = $back;
        $this->setSlugFromBackAndPack();

        return $this;
    }

    public function getPack(): ?string
    {
        return $this->pack;
    }

    public function setPack(?string $pack): static
    {
        $this->pack = $pack;
        $this->setSlugFromBackAndPack();

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

    // Its slug written from its back and its pack as soon as both are known, before validation so that UniqueEntity sees it, and again on persist as a safety net
    #[ORM\PrePersist]
    public function setSlugFromBackAndPack(): void
    {
        $this->slug = $this->slugOf() ?? $this->slug;
    }

    // "aube-hur" for the dawn back of the pack "HUR", null until both are known
    private function slugOf(): ?string
    {
        $back = $this->back?->getSlug();

        return null === $back || null === $this->pack ? null : $back . '-' . strtolower($this->pack);
    }

    public function getModification(): \DateTimeImmutable
    {
        return $this->modification;
    }

    // One file per back and pack, named after both
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
        return trim(($this->back?->getName() ?? '') . ' - ' . ($this->pack ?? ''), ' -');
    }
}
