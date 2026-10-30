<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Command;

use c975L\CardBundle\Export\CardExporter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

// The export from the terminal, the same run the dashboard tile queues: everything the site exports, only what changed since the last run - a target to try one card or one series, --force to shoot everything again
#[AsCommand(name: 'card:export', description: 'Exporte les cartes (papier et réseaux sociaux), seulement ce qui a changé depuis le dernier export')]
class CardExportCommand
{
    public function __construct(
        private readonly CardExporter $cardExporter,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Ce que le site sait cibler : une série, une carte (heros/12, characters...) - tout si omis')]
        string $target = '',
        #[Option('Tout refaire, même ce qui n\'a pas changé')]
        bool $force = false,
    ): int {
        $io->title('Export des cartes');

        try {
            $start = microtime(true);
            $counts = $this->cardExporter->export('' === $target ? null : $target, $force, $io->section(...));
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d produits, %d inchangés, %d retirés (%d s).', $counts['made'], $counts['kept'], $counts['removed'], (int) round(microtime(true) - $start)));

        return Command::SUCCESS;
    }
}
