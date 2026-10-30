<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Management;

use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Controller\Management\CardExportController;
use c975L\CardBundle\Export\CardExporter;
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
    ) {
    }

    public function getGuidedProjects(): array
    {
        // The export follows its tile, shown only where Chrome and Ghostscript are installed: elsewhere it would highlight nothing
        return [$this->backProject(), ...($this->cardExporter->isAvailable() ? [$this->exportProject()] : [])];
    }

    // Making a variant of a pack's back, which a player then wins and chooses for the cards of that pack
    private function backProject(): array
    {
        $steps = [['key' => 'open', 'url' => $this->indexUrl(CardBackCrudController::class)]];
        foreach (['new' => '.action-new', 'pack' => '#CardBack_pack', 'name' => '#CardBack_name', 'image' => '#CardBack_imageFile_file', 'tone' => '#CardBack_tone', 'effect' => '#CardBack_effect', 'price' => '#CardBack_price', 'published' => '#CardBack_published'] as $key => $highlight) {
            $steps[] = ['key' => $key, 'highlight' => $highlight];
        }
        $steps[] = ['key' => 'done', 'highlight' => '.action-saveAndReturn'];

        return self::project('card_back', 11010, (string) $this->configService->get('site-role-editor'), $steps);
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
