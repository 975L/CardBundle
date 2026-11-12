<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// What a back, a skin and their pictures share: an image uploaded under UiBundle's "block_media" mapping, card-sized, the class naming its file (see VichMediaNamableInterface) and stamping $modification
trait BackImageTrait
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $image = null;

    // Never persisted: Vich converts the upload to webp and UiBundle cuts its "-thumb"/"-highres" siblings
    #[Vich\UploadableField(mapping: 'block_media', fileNameProperty: 'image')]
    private ?File $imageFile = null;

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

    // Drawn no wider than a card
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
}
