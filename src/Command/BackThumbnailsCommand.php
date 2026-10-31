<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Command;

use c975L\CardBundle\Service\BackThumbnailMaker;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

// The backs' thumbnails from the terminal, the same run as the dashboard tile: every published variant, or those named, a delay to catch an animation at a better moment
#[AsCommand(name: 'card:back-thumbnails', description: 'Fait la vignette de chaque dos, photographiée sur la page du site qui le montre seul')]
class BackThumbnailsCommand
{
    public function __construct(
        private readonly BackThumbnailMaker $backThumbnailMaker,
    ) {
    }

    /** @param list<string> $slugs */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Les dos à refaire, par leur slug - tous si omis')]
        array $slugs = [],
        #[Option('Le temps laissé au dos pour bouger avant la photo, en ms')]
        int $delay = 4000,
    ): int {
        $io->title('Vignettes des dos');
        if (!$this->backThumbnailMaker->isAvailable()) {
            $io->error('Il faut Chrome et une page du site montrant un dos seul (CardBackPageProviderInterface).');

            return Command::FAILURE;
        }

        $made = $this->backThumbnailMaker->make($slugs, $delay);
        foreach ($made as $slug => $image) {
            $io->writeln(\sprintf('%s : %s', $slug, $image ?? 'pas de page'));
        }

        // A slug mistyped, unpublished or the standard back's is no variant to shoot
        $unknown = array_diff($slugs, array_keys($made));
        if ([] !== $unknown) {
            $io->warning(\sprintf('Aucun dos publié pour : %s', implode(', ', $unknown)));
        }
        $io->success('Vignettes faites.');

        return Command::SUCCESS;
    }
}
