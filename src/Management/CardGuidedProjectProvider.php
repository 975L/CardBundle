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
use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's guided project, in the 11000 block GuidedProjectProviderInterface reserves it. Only the opening step carries an url: from there the parcours walks the screen the user has been sent to, highlighting the field they are meant to use next
class CardGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [$this->backProject()];
    }

    // Making a variant of a pack's back, which a player then wins and chooses for the cards of that pack
    private function backProject(): array
    {
        $steps = [['key' => 'open', 'url' => $this->indexUrl(CardBackCrudController::class)]];
        foreach (['new' => '.action-new', 'pack' => '#CardBack_pack', 'name' => '#CardBack_name', 'image' => '#CardBack_imageFile', 'tone' => '#CardBack_tone', 'effect' => '#CardBack_effect', 'published' => '#CardBack_published'] as $key => $highlight) {
            $steps[] = ['key' => $key, 'highlight' => $highlight];
        }
        $steps[] = ['key' => 'done', 'highlight' => '.action-saveAndReturn'];

        return [
            'slug' => 'card-back',
            'label' => 'label.guided_project_card_back',
            'description' => 'description.guided_project_card_back',
            'translation_domain' => 'card',
            'order' => 11010,
            'role' => (string) $this->configService->get('site-role-editor'),
            'steps' => array_map(static function (array $step): array {
                $key = $step['key'];
                unset($step['key']);

                return [
                    'label' => 'label.guided_step_card_back_' . $key,
                    'description' => 'description.guided_step_card_back_' . $key,
                    'narration' => 'narration.guided_step_card_back_' . $key,
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
