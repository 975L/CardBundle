<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Controller\Management;

use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Service\BackStyles;
use c975L\CardBundle\Service\CardCatalog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class CardBackCrudControllerTest extends TestCase
{
    use CrudFieldsTrait;

    // The previews name their template as a string, which nothing compiles: a renamed file breaks the listing, and that path is also what a site overrides
    public function testThePreviewTemplateExists(): void
    {
        $root = \dirname(__DIR__, 3);
        foreach (['CardBackCrudController', 'CardBackPictureCrudController'] as $controller) {
            $this->assertSame(1, preg_match("#'@c975LCard/([^']+)'#", (string) file_get_contents($root . '/src/Controller/Management/' . $controller . '.php'), $matches));
            $this->assertFileExists($root . '/templates/' . $matches[1]);
        }
    }

    // The slug is typed on creation and locked afterwards: rewards, codes and the stores name the back by it
    public function testTheSlugIsLockedOnceCreated(): void
    {
        $this->assertFalse($this->fields(Crud::PAGE_NEW)['slug']->getAsDto()->getFormTypeOption('disabled'));

        $slug = $this->fields(Crud::PAGE_EDIT)['slug']->getAsDto();
        $this->assertTrue($slug->getFormTypeOption('disabled'));
        $this->assertEquals(t('help.back_slug_locked', [], 'card'), $slug->getHelp());
    }

    // The tone and the effect offer what the site draws, "none" alone on a site offering nothing
    public function testToneAndEffectOfferWhatTheSiteDraws(): void
    {
        $fields = $this->fields(Crud::PAGE_NEW);

        foreach (['tone', 'effect'] as $property) {
            $this->assertSame([CardBack::NO_STYLE], array_keys((array) $fields[$property]->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
        }
    }

    // One standard back per site: the one saved as such unmarks the others, flushed with it
    public function testSavingAStandardBackUnmarksTheOthers(): void
    {
        $saved = new CardBack()->setStandard(true);
        $other = new CardBack()->setStandard(true);
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn([$saved, $other]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects($this->exactly(2))->method('flush');

        $this->controller()->persistEntity($entityManager, $saved);
        $this->assertTrue($saved->isStandard());
        $this->assertFalse($other->isStandard());

        $other->setStandard(true);
        $this->controller()->updateEntity($entityManager, $saved);
        $this->assertFalse($other->isStandard());
    }

    // A back that is not the standard one leaves the others as they are, unread
    public function testSavingAVariantLeavesTheStandardBack(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');

        $this->controller()->updateEntity($entityManager, new CardBack());
    }

    // A back someone holds is never deleted, its purchases going with it in cascade: the editor is told to unpublish it, the batch delete being gone
    public function testABackHeldIsNotDeleted(): void
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('count')->willReturn(1);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects($this->never())->method('remove');
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack([$request]);
        $container = new Container();
        $container->set('request_stack', $requestStack);
        $controller = $this->controller();
        $controller->setContainer($container);

        $controller->deleteEntity($entityManager, new CardBack());

        $this->assertSame(['label.back_held_not_deleted'], $session->getFlashBag()->get('danger'));
        $this->assertNull($controller->configureActions(Actions::new())->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE));
    }

    // A back nobody holds is deleted as any content
    public function testABackNobodyHoldsIsDeleted(): void
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('count')->willReturn(0);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects($this->once())->method('remove');

        $this->controller()->deleteEntity($entityManager, new CardBack());
    }

    // The fields a page shows, by property
    /**
     * @return array<string, FieldInterface>
     */
    private function fields(string $pageName): array
    {
        return $this->fieldsOf($this->controller(), $pageName);
    }

    private function controller(): CardBackCrudController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CardBackCrudController($this->createStub(ConfigServiceInterface::class), $translator, new CardCatalog([], [], $this->createStub(ConfigServiceInterface::class)), new BackStyles([]));
    }
}
