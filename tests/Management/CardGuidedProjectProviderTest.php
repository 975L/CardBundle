<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Management;

use c975L\CardBundle\Controller\Management\BackThumbnailsController;
use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Controller\Management\CardBackPictureCrudController;
use c975L\CardBundle\Controller\Management\CardExportController;
use c975L\CardBundle\Export\CardExporter;
use c975L\CardBundle\Management\CardGuidedProjectProvider;
use c975L\CardBundle\Management\MenuProvider;
use c975L\CardBundle\Service\BackStyles;
use c975L\CardBundle\Service\BackThumbnailMaker;
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
        $arguments = [$this->createStub(ConfigServiceInterface::class), $this->createStub(TranslatorInterface::class), new CardCatalog([], [], $this->createStub(ConfigServiceInterface::class))];
        foreach (['CardBack' => [new CardBackCrudController(...$arguments, backStyles: new BackStyles([])), 0], 'CardBackPicture' => [new CardBackPictureCrudController(...$arguments), 1]] as $entity => [$controller, $index]) {
            $properties = array_map(
                static fn (FieldInterface $field): string => $field->getAsDto()->getProperty(),
                array_filter([...$controller->configureFields(Crud::PAGE_NEW)], static fn (FieldInterface $field): bool => $field->getAsDto()->isDisplayedOn(Crud::PAGE_NEW)),
            );

            $highlighted = 0;
            foreach ($this->projects(false)[$index]['steps'] as $step) {
                // A Vich upload carries no id of its own, its file input being "<property>_file"
                if (1 === preg_match('/^#' . $entity . '_([a-zA-Z]+)(?:_file)?$/', $step['highlight'] ?? '', $matches)) {
                    $this->assertContains($matches[1], $properties, $step['label']);
                    ++$highlighted;
                }
            }
            $this->assertGreaterThan(0, $highlighted, $entity);
        }
    }

    // The pictures' project follows the backs', in the same block and for the same role
    public function testThePicturesProjectFollowsTheBacks(): void
    {
        $project = $this->projects(false)[1];

        $this->assertSame('card-back-picture', $project['slug']);
        $this->assertSame(11015, $project['order']);
        $this->assertSame('ROLE_EDITOR', $project['role']);
        $this->assertSame('label.guided_step_card_back_picture_open', $project['steps'][0]['label']);
        $this->assertArrayHasKey('url', $project['steps'][0]);
    }

    // The export project follows its tile: offered where the export can run, to whoever the tile lets in, and nowhere else
    public function testTheExportProjectFollowsTheTile(): void
    {
        $this->assertCount(2, $this->projects(false));

        $project = $this->projects(true)[2];
        $this->assertSame('card-export', $project['slug']);
        $this->assertSame(11020, $project['order']);
        $this->assertSame(CardExportController::ROLE, $project['role']);
        $this->assertSame(['label.guided_step_card_export_open', 'label.guided_step_card_export_run', 'label.guided_step_card_export_follow'], array_column($project['steps'], 'label'));
        $this->assertSame('/management', $project['steps'][0]['url']);
    }

    // The thumbnails project follows its tile too: offered where they can be made, after the export, and nowhere else
    public function testTheThumbnailsProjectFollowsTheTile(): void
    {
        $this->assertNotContains('card-back-thumbnails', array_column($this->projects(true), 'slug'));

        $project = $this->projects(true, true)[3];
        $this->assertSame('card-back-thumbnails', $project['slug']);
        $this->assertSame(11025, $project['order']);
        $this->assertSame(BackThumbnailsController::ROLE, $project['role']);
        $this->assertSame(['label.guided_step_card_back_thumbnails_open', 'label.guided_step_card_back_thumbnails_run', 'label.guided_step_card_back_thumbnails_done'], array_column($project['steps'], 'label'));
        $this->assertSame('form[action$="/card/back-thumbnails"] button', $project['steps'][1]['highlight']);
        $this->assertSame('/management', $project['steps'][0]['url']);
    }

    // The menu entries ask for the very role the screen and the project ask for
    public function testTheMenuEntryAsksForTheEditorsRole(): void
    {
        $menus = new MenuProvider($this->configService())->getMenus();

        $this->assertSame(CardBackCrudController::class, $menus['card_back']['controller']);
        $this->assertSame('ROLE_EDITOR', $menus['card_back']['role']);
        $this->assertSame(CardBackPictureCrudController::class, $menus['card_back_picture']['controller']);
        $this->assertSame('ROLE_EDITOR', $menus['card_back_picture']['role']);
    }

    /** @return array<string, mixed> */
    private function project(): array
    {
        return $this->projects(false)[0];
    }

    /** @return list<array<string, mixed>> */
    private function projects(bool $exportAvailable, bool $thumbnailsAvailable = false): array
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
        $backThumbnailMaker = $this->createStub(BackThumbnailMaker::class);
        $backThumbnailMaker->method('isAvailable')->willReturn($thumbnailsAvailable);

        return new CardGuidedProjectProvider($adminUrlGenerator, $this->configService(), $urlGenerator, $cardExporter, $backThumbnailMaker)->getGuidedProjects();
    }

    private function configService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-role-editor', 'ROLE_EDITOR']]);

        return $configService;
    }
}
