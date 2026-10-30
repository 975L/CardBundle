<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Management;

use c975L\CardBundle\Controller\Management\CardExportController;
use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Export\ExportStatus;
use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// The export as a dashboard tile, shown only where it can run: Chrome and Ghostscript are installed locally and not on the managed production server
class CardExportShortcutProvider implements ShortcutProviderInterface
{
    // The tile's word for each state of the run (see ExportStatus::current())
    private const array STATES = ['running' => 'label.export_running', 'requested' => 'label.export_requested', 'failed' => 'label.export_failed'];

    public function __construct(
        private readonly CardExporter $cardExporter,
        private readonly ExportStatus $status,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getShortcuts(): array
    {
        if (!$this->cardExporter->isAvailable()) {
            return [];
        }

        $state = $this->state();

        return [[
            'label' => $this->translator->trans('label.export_cards', [], 'card') . ('' === $state ? '' : \sprintf(' (%s)', $state)),
            'icon' => 'fas fa-file-export',
            'route' => CardExportController::EXPORT_ROUTE,
            'active' => false,
            'role' => CardExportController::ROLE,
            'category' => self::CATEGORY_EXPORT,
            'method' => 'POST',
        ]];
    }

    // What is under way comes first, the date of the last run when nothing runs
    private function state(): string
    {
        $current = $this->status->current();
        if (null !== $current) {
            return $this->translator->trans(self::STATES[$current['state']], ['%detail%' => $this->detail($current['detail'])], 'card');
        }
        $stateFile = $this->projectDir . '/' . CardExporter::STATE_FILE;

        return is_file($stateFile)
            ? $this->translator->trans('label.export_last', ['%date%' => $this->status->localTime((int) filemtime($stateFile), 'd/m/Y H:i')], 'card')
            : '';
    }

    // The status's own words translated, a file's path or an error message shown as it is
    private function detail(string $detail): string
    {
        if (ExportStatus::PREPARING === $detail) {
            return $this->translator->trans('label.export_preparing', [], 'card');
        }

        return str_starts_with($detail, ExportStatus::STALE)
            ? $this->translator->trans('label.export_stale', ['%time%' => substr($detail, \strlen(ExportStatus::STALE))], 'card')
            : $detail;
    }
}
