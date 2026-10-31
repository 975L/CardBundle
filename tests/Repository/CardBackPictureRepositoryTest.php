<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Repository;

use c975L\CardBundle\Entity\CardBackPicture;
use c975L\CardBundle\Repository\CardBackPictureRepository;
use PHPUnit\Framework\TestCase;

// The pictures are read as two scalars, no entity hydrated: the query is checked on its DQL, stopped once written
class CardBackPictureRepositoryTest extends TestCase
{
    use RegistryStubTrait;

    // A pack's pictures by back id, a row whose upload is missing left out so its pack's own picture is drawn
    public function testAPacksImagesAreReadByBackIdWithoutTheMissingOnes(): void
    {
        try {
            new CardBackPictureRepository($this->stoppedRegistry(CardBackPicture::class))->findImagesOfPack('hur');
            $this->fail('The query was never written.');
        } catch (\LogicException $exception) {
            $this->assertStringStartsWith('SELECT IDENTITY(p.back) AS back, p.image FROM', $exception->getMessage());
            $this->assertStringEndsWith('WHERE p.pack = :pack AND p.image IS NOT NULL', $exception->getMessage());
        }
    }
}
