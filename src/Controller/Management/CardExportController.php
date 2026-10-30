<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Export\ExportStatus;
use c975L\CardBundle\Message\ExportCardsMessage;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The export ordered from the dashboard (see CardExportShortcutProvider): queued and the hand given back at once, the run taking minutes the first time and seconds once only an edit is left to redo
class CardExportController extends AbstractController
{
    // EasyAdmin prefixes the name with the Dashboard's, which gives management_XXX
    public const string EXPORT_ROUTE = 'management_card_export';

    // Every card at once, published or not, is no editing screen
    public const string ROLE = 'ROLE_SUPER_ADMIN';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly CardExporter $cardExporter,
        private readonly ExportStatus $status,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(path: '/card/export', name: 'card_export', options: ['methods' => ['POST']])]
    public function export(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(self::ROLE);

        if (!$this->isCsrfTokenValid(self::EXPORT_ROUTE, $request->request->get('_token')) || !$this->cardExporter->isAvailable()) {
            return $this->redirectToRoute('management');
        }

        // Two runs at once means two Chrome over the same files
        if ($this->status->isRunning()) {
            $this->addFlash('warning', $this->translator->trans('flash.export_running', [], 'card'));

            return $this->redirectToRoute('management');
        }

        $this->status->request();
        $this->messageBus->dispatch(new ExportCardsMessage());
        $this->addFlash('success', $this->translator->trans('flash.export_started', [], 'card'));

        return $this->redirectToRoute('management');
    }
}
