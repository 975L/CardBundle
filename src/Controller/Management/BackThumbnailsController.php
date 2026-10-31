<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Controller\Management;

use c975L\CardBundle\Service\BackThumbnailMaker;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

// The dashboard tile making the backs' thumbnails, run at once: a few backs, a few seconds each (see BackThumbnailMaker)
class BackThumbnailsController extends AbstractController
{
    // EasyAdmin prefixes the name with the Dashboard's, which gives management_XXX
    public const string ROUTE = 'management_card_back_thumbnails';

    public const string ROLE = 'ROLE_SUPER_ADMIN';

    public function __construct(
        private readonly BackThumbnailMaker $backThumbnailMaker,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(path: '/card/back-thumbnails', name: 'card_back_thumbnails', options: ['methods' => ['POST']])]
    public function make(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(self::ROLE);

        if (!$this->isCsrfTokenValid(self::ROUTE, $request->request->get('_token')) || !$this->backThumbnailMaker->isAvailable()) {
            return $this->redirectToRoute('management');
        }

        set_time_limit(0);
        $made = array_filter($this->backThumbnailMaker->make());
        $this->addFlash('success', $this->translator->trans('flash.back_thumbnails_made', ['%count%' => \count($made)], 'card'));

        return $this->redirectToRoute('management');
    }
}
