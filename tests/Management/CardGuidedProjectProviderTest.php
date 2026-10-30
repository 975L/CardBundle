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
use c975L\CardBundle\Controller\Management\CardExportController;
use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Management\CardGuidedProjectProvider;
use c975L\CardBundle\Management\MenuProvider;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        $controller = new CardBackCrudController($this->createStub(ConfigServiceInterface::class), $this->createStub(TranslatorInterface::class), new CardCatalog([], [], $this->createStub(ConfigServiceInterface::class)));
        $properties = array_map(
            static fn (FieldInterface $field): string => $field->getAsDto()->getProperty(),
            array_filter([...$controller->configureFields(Crud::PAGE_NEW)], static fn (FieldInterface $field): bool => $field->getAsDto()->isDisplayedOn(Crud::PAGE_NEW)),
        );

        $highlighted = 0;
        foreach ($this->project()['steps'] as $step) {
            // A Vich upload carries no id of its own, its file input being "<property>_file"
            if (1 === preg_match('/^#CardBack_([a-zA-Z]+)(?:_file)?$/', $step['highlight'] ?? '', $matches)) {
                $this->assertContains($matches[1], $properties, $step['label']);
                ++$highlighted;
            }
        }
        $this->assertGreaterThan(0, $highlighted);
    }

    // The export project follows its tile: offered where the export can run, to whoever the tile lets in, and nowhere else
    public function testTheExportProjectFollowsTheTile(): void
    {
        $this->assertCount(1, $this->projects(false));

        $project = $this->projects(true)[1];
        $this->assertSame('card-export', $project['slug']);
        $this->assertSame(11020, $project['order']);
        $this->assertSame(CardExportController::ROLE, $project['role']);
        $this->assertSame(['label.guided_step_card_export_open', 'label.guided_step_card_export_run', 'label.guided_step_card_export_follow'], array_column($project['steps'], 'label'));
        $this->assertSame('/management', $project['steps'][0]['url']);
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
        return $this->projects(false)[0];
    }

    /** @return list<array<string, mixed>> */
    private function projects(bool $exportAvailable): array
    {
        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/card-back');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/management');
        $cardExporter = $this->createStub(CardExporter::class);
        $cardExporter->method('isAvailable')->willReturn($exportAvailable);

        return new CardGuidedProjectProvider($adminUrlGenerator, $this->configService(), $urlGenerator, $cardExporter)->getGuidedProjects();
    }

    private function configService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-role-editor', 'ROLE_EDITOR']]);

        return $configService;
    }
}
