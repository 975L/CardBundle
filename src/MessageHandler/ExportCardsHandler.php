<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\MessageHandler;

use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Export\ExportStatus;
use c975L\CardBundle\Message\ExportCardsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// Runs the export in the worker the local server already starts, the tile having handed back to the browser at once
#[AsMessageHandler]
class ExportCardsHandler
{
    public function __construct(
        private readonly CardExporter $cardExporter,
        private readonly ExportStatus $status,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExportCardsMessage $message): void
    {
        // Handled in the request when the transport is sync, where max_execution_time would cut the run and skip its cleanup
        set_time_limit(0);

        // The failure is logged and not thrown: a retry means more minutes of Chrome for a run that failed on a missing binary or an unwritable folder, which a second attempt does not fix
        try {
            $counts = $this->cardExporter->export(force: $message->force, notify: function (string $step): void {
                $this->logger->info('Export des cartes : ' . $step);
            });
            $this->logger->info(\sprintf('Export des cartes terminé : %d produits, %d inchangés, %d retirés', $counts['made'], $counts['kept'], $counts['removed']));
        } catch (\Throwable $exception) {
            $this->logger->error(\sprintf('Export des cartes en échec : %s', $exception->getMessage()));
            $this->status->fail($exception->getMessage());
        }
    }
}
