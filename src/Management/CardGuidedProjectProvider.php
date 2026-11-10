<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Management;

use c975L\CardBundle\Controller\Management\BackThumbnailsController;
use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Controller\Management\CardBackPictureCrudController;
use c975L\CardBundle\Controller\Management\CardExportController;
use c975L\CardBundle\Controller\Management\CardSkinCrudController;
use c975L\CardBundle\Controller\Management\CardSkinPictureCrudController;
use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Service\BackThumbnailMaker;
use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// This bundle's guided projects, in the 11000 block GuidedProjectProviderInterface reserves them. Only the opening step carries an url: from there the parcours walks the screen the user has been sent to, highlighting the field they are meant to use next
class CardGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CardExporter $cardExporter,
        private readonly BackThumbnailMaker $backThumbnailMaker,
    ) {
    }

    public function getGuidedProjects(): array
    {
        // The export and the thumbnails follow their tiles, shown only where Chrome (and Ghostscript for the export) is installed: elsewhere they would highlight nothing
        return [
            $this->backProject(),
            $this->pictureProject(),
            $this->skinProject(),
            $this->skinPictureProject(),
            ...($this->cardExporter->isAvailable() ? [$this->exportProject()] : []),
            ...($this->backThumbnailMaker->isAvailable() ? [$this->thumbnailsProject()] : []),
        ];
    }

    // Making a variant of the backs, which a player then wins or buys for all the packs and chooses for all their cards
    private function backProject(): array
    {
        return self::project('card_back', 11010, (string) $this->configService->get('site-role-editor'), self::formSteps(
            $this->indexUrl(CardBackCrudController::class),
            ['name' => '#CardBack_name', 'tone' => '#CardBack_tone', 'effect' => '#CardBack_effect', 'thumbnail' => '#CardBack_imageFile_file', 'price' => '#CardBack_price', 'published' => '#CardBack_published', 'standard' => '#CardBack_standard'],
        ));
    }

    // Giving a back its picture for one pack, the pack's own being drawn in its tone otherwise
    private function pictureProject(): array
    {
        return self::project('card_back_picture', 11015, (string) $this->configService->get('site-role-editor'), self::formSteps(
            $this->indexUrl(CardBackPictureCrudController::class),
            ['back' => '#CardBackPicture_back', 'pack' => '#CardBackPicture_pack', 'image' => '#CardBackPicture_imageFile_file'],
        ));
    }

    // Making a skin of the card faces, which a player then wins or buys pack by pack and chooses for that pack's cards
    private function skinProject(): array
    {
        return self::project('card_skin', 11030, (string) $this->configService->get('site-role-editor'), self::formSteps(
            $this->indexUrl(CardSkinCrudController::class),
            ['name' => '#CardSkin_name', 'thumbnail' => '#CardSkin_imageFile_file', 'price' => '#CardSkin_price', 'published' => '#CardSkin_published'],
        ));
    }

    // Giving a skin its picture for one card, the packs it dresses being those its pictures are for
    private function skinPictureProject(): array
    {
        return self::project('card_skin_picture', 11035, (string) $this->configService->get('site-role-editor'), self::formSteps(
            $this->indexUrl(CardSkinPictureCrudController::class),
            ['skin' => '#CardSkinPicture_skin', 'pack' => '#CardSkinPicture_pack', 'reference' => '#CardSkinPicture_reference', 'image' => '#CardSkinPicture_imageFile_file'],
        ));
    }

    // A form's parcours: open the listing, create, each field in turn, save
    /**
     * @param array<string, string> $fields
     *
     * @return list<array<string, string>>
     */
    private static function formSteps(string $url, array $fields): array
    {
        $steps = [['key' => 'open', 'url' => $url], ['key' => 'new', 'highlight' => '.action-new']];
        foreach ($fields as $key => $highlight) {
            $steps[] = ['key' => $key, 'highlight' => $highlight];
        }
        $steps[] = ['key' => 'done', 'highlight' => '.action-saveAndReturn'];

        return $steps;
    }

    // Exporting the cards from the dashboard tile, the run going on in the Messenger worker while the tile tells where it stands
    private function exportProject(): array
    {
        return self::project('card_export', 11020, CardExportController::ROLE, [
            ['key' => 'open', 'url' => $this->urlGenerator->generate('management')],
            ['key' => 'run', 'highlight' => 'form[action$="/card/export"] button'],
            ['key' => 'follow'],
        ]);
    }

    // Making every back's thumbnail from the dashboard tile, each shot on the site's page showing it alone
    private function thumbnailsProject(): array
    {
        return self::project('card_back_thumbnails', 11025, BackThumbnailsController::ROLE, [
            ['key' => 'open', 'url' => $this->urlGenerator->generate('management')],
            ['key' => 'run', 'highlight' => 'form[action$="/card/back-thumbnails"] button'],
            ['key' => 'done'],
        ]);
    }

    // A project and its steps, every key named after its slug
    /** @param list<array<string, string>> $steps */
    private static function project(string $name, int $order, string $role, array $steps): array
    {
        return [
            'slug' => str_replace('_', '-', $name),
            'label' => 'label.guided_project_' . $name,
            'description' => 'description.guided_project_' . $name,
            'translation_domain' => 'card',
            'order' => $order,
            'role' => $role,
            'steps' => array_map(static function (array $step) use ($name): array {
                $key = $step['key'];
                unset($step['key']);

                return [
                    'label' => 'label.guided_step_' . $name . '_' . $key,
                    'description' => 'description.guided_step_' . $name . '_' . $key,
                    'narration' => 'narration.guided_step_' . $name . '_' . $key,
                    ...$step,
                ];
            }, $steps),
        ];
    }

    // The listing a parcours opens on
    private function indexUrl(string $controller): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controller)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;
    }
}
