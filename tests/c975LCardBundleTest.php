<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests;

use c975L\CardBundle\c975LCardBundle;
use c975L\CardBundle\Contract\CardBackPageProviderInterface;
use c975L\CardBundle\Contract\CardCatalogInterface;
use c975L\CardBundle\Contract\CardPackProviderInterface;
use c975L\CardBundle\Contract\HolderStoreInterface;
use c975L\CardBundle\Management\MenuProvider;
use c975L\CardBundle\Service\CardHolder;
use c975L\CardBundle\Service\CardRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

class c975LCardBundleTest extends TestCase
{
    // Loaded the way the kernel does, which also checks that config/services.yaml parses and wires
    public function testLoadExtensionImportsServicesYaml(): void
    {
        $container = new ContainerBuilder();

        new c975LCardBundle()->getContainerExtension()->load([], $container);

        $this->assertTrue($container->hasDefinition(MenuProvider::class));
        $this->assertTrue($container->hasDefinition(CardHolder::class));
    }

    // The points a site plugs into are tagged rather than aliased, which is what spares the site any configuration: an attribute dropped would leave its implementation silently unread
    public function testTheSiteContractsAreTaggedByAutoconfiguration(): void
    {
        $tags = [
            HolderStoreInterface::class => 'c975l.card_holder_store',
            CardCatalogInterface::class => 'c975l.card_catalog',
            CardPackProviderInterface::class => 'c975l.card_pack_provider',
            CardBackPageProviderInterface::class => 'c975l.card_back_page_provider',
        ];
        foreach ($tags as $interface => $tag) {
            $attributes = new \ReflectionClass($interface)->getAttributes(AutoconfigureTag::class);
            $this->assertCount(1, $attributes, $interface);
            $this->assertSame($tag, $attributes[0]->getArguments()[0]);
        }
    }

    // The limiter is optional: a reference that is not would break every site that does not declare it, at the container's compilation
    public function testTheLimiterIsAnOptionalReference(): void
    {
        $container = new ContainerBuilder();
        new c975LCardBundle()->getContainerExtension()->load([], $container);

        $reference = $container->getDefinition(CardRequest::class)->getArgument('$limiterFactory');

        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('limiter.ui_favorite', (string) $reference);
        $this->assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $reference->getInvalidBehavior());
    }

    public function testGetPathReturnsTheBundleRootDirectory(): void
    {
        $this->assertSame(\dirname(__DIR__), new c975LCardBundle()->getPath());
    }
}
