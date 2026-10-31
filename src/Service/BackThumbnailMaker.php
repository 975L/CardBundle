<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\CardBundle\Contract\CardBackPageProviderInterface;
use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Export\ChromeSession;
use c975L\CardBundle\Export\PageRenderer;
use c975L\CardBundle\Repository\CardBackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// Each back's thumbnail, the one the basket shows, shot from the site's page showing it alone (see CardBackPageProviderInterface) by a headless Chrome and stored as an upload in the back office would be. Local only, Chrome being no Composer dependency and absent from the managed production server
class BackThumbnailMaker
{
    /** @param iterable<CardBackPageProviderInterface> $pageProviders */
    public function __construct(
        #[AutowireIterator('c975l.card_back_page_provider')]
        private readonly iterable $pageProviders,
        private readonly CardBackRepository $cardBackRepository,
        private readonly PageRenderer $renderer,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Whether thumbnails can be made here: a page to shoot and a browser to shoot it
    public function isAvailable(): bool
    {
        return [] !== iterator_to_array($this->pageProviders, false) && null !== $this->renderer->browser();
    }

    // Every published variant's thumbnail, or those named, after letting each back move the given time: an animated back shot at its first frame shows none of its effect. Returns slug => the stored image, null for a back the site cannot show alone
    /**
     * @param list<string> $slugs
     *
     * @return array<string, string|null>
     */
    public function make(array $slugs = [], int $delay = 4000): array
    {
        $browser = $this->renderer->browser() ?? throw new \RuntimeException(\sprintf('No browser to shoot the backs. Expected one of: %s.', implode(', ', PageRenderer::BROWSERS)));
        $workDir = $this->projectDir . '/var/card-thumbnails';
        $filesystem = new Filesystem();
        $filesystem->mkdir($workDir);

        $made = [];
        $chrome = new ChromeSession($browser, $workDir . '/profile');
        try {
            foreach ($this->cardBackRepository->findPublished() as $back) {
                if ([] !== $slugs && !\in_array($back->getSlug(), $slugs, true)) {
                    continue;
                }
                $made[(string) $back->getSlug()] = $this->shoot($chrome, $back, $workDir, $delay);
            }
        } finally {
            $chrome->close();
            $filesystem->remove($workDir . '/profile');
        }

        return $made;
    }

    // One back shot and stored through Vich, which converts it to webp and names it (see CardBack::getVichMediaPath())
    private function shoot(ChromeSession $chrome, CardBack $back, string $workDir, int $delay): ?string
    {
        $url = $this->pageOf($back);
        if (null === $url) {
            return null;
        }

        $chrome->open($url, 400, 800);
        // The profiler's bar of a dev server laid over nothing that is shot, then the back left to move
        $chrome->evaluate(\sprintf('document.querySelectorAll(".sf-toolbar").forEach((bar) => bar.remove()); new Promise((resolve) => setTimeout(resolve, %d))', $delay));
        $file = $workDir . '/' . $back->getSlug() . '.png';
        $chrome->screenshotElement('.card-back', $file);

        $back->setImageFile(new UploadedFile($file, (string) $back->getSlug() . '.png', 'image/png', null, true));
        $this->entityManager->flush();

        return $back->getImage();
    }

    // The first page the site gives for it
    private function pageOf(CardBack $back): ?string
    {
        foreach ($this->pageProviders as $provider) {
            $url = $provider->getBackPageUrl($back);
            if (null !== $url) {
                return $url;
            }
        }

        return null;
    }
}
