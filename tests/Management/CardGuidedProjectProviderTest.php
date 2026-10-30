<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Management;

use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Management\CardGuidedProjectProvider;
use c975L\CardBundle\Management\MenuProvider;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class CardGuidedProjectProviderTest extends TestCase
{
    // In the 11000 block GuidedProjectProviderInterface reserves this bundle, and offered to whoever the screen itself lets in
    public function testTheProjectSitsInItsBlockForTheEditors(): void
    {
        $project = $this->project();

        $this->assertSame('card-back', $project['slug']);
        $this->assertSame('card', $project['translation_domain']);
        $this->assertGreaterThanOrEqual(11000, $project['order']);
        $this->assertLessThan(12000, $project['order']);
        $this->assertSame('ROLE_EDITOR', $project['role']);
    }

    // Only the opening step sends the user somewhere: the others walk the screen they were sent to
    public function testOnlyTheOpeningStepCarriesAnUrl(): void
    {
        $steps = $this->project()['steps'];

        $this->assertSame('label.guided_step_card_back_open', $steps[0]['label']);
        $this->assertArrayHasKey('url', $steps[0]);
        $this->assertSame('label.guided_step_card_back_done', $steps[array_key_last($steps)]['label']);
        foreach (\array_slice($steps, 1) as $step) {
            $this->assertArrayNotHasKey('url', $step);
        }
    }

    // A highlighted field the form no longer shows leaves the step pointing at nothing, which no other test would see
    public function testEveryHighlightedFieldIsOnTheForm(): void
    {
        $controller = new CardBackCrudController($this->createStub(ConfigServiceInterface::class), $this->createStub(TranslatorInterface::class), new CardCatalog([], []));
        $properties = array_map(
            static fn (FieldInterface $field): string => $field->getAsDto()->getProperty(),
            array_filter([...$controller->configureFields(Crud::PAGE_NEW)], static fn (FieldInterface $field): bool => $field->getAsDto()->isDisplayedOn(Crud::PAGE_NEW)),
        );

        $highlighted = 0;
        foreach ($this->project()['steps'] as $step) {
            if (1 === preg_match('/^#CardBack_(\w+)$/', $step['highlight'] ?? '', $matches)) {
                $this->assertContains($matches[1], $properties, $step['label']);
                ++$highlighted;
            }
        }
        $this->assertGreaterThan(0, $highlighted);
    }

    // The menu entry asks for the very role the screen and the project ask for
    public function testTheMenuEntryAsksForTheEditorsRole(): void
    {
        $menus = new MenuProvider($this->configService())->getMenus();

        $this->assertSame(CardBackCrudController::class, $menus['card_back']['controller']);
        $this->assertSame('ROLE_EDITOR', $menus['card_back']['role']);
    }

    /** @return array<string, mixed> */
    private function project(): array
    {
        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/card-back');

        $projects = new CardGuidedProjectProvider($adminUrlGenerator, $this->configService())->getGuidedProjects();
        $this->assertCount(1, $projects);

        return $projects[0];
    }

    private function configService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-role-editor', 'ROLE_EDITOR']]);

        return $configService;
    }
}
