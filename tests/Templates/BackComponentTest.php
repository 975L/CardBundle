<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Templates;

use c975L\CardBundle\Service\AssetProvider;
use PHPUnit\Framework\TestCase;

// The contract between the component, back.js and sass/_back.scss, nothing rendered: the classes the effects are read off, the controller's name, and the files UiBundle's layout is handed
class BackComponentTest extends TestCase
{
    // The effect and the tone written as classes, the controller left out on paper
    public function testTheComponentWritesWhatTheControllerReads(): void
    {
        $template = $this->read('templates/components/Back.html.twig');

        $this->assertStringContainsString("'card-back card-back--' ~ effect ~ ' card-back--tone-' ~ tone", $template);
        $this->assertStringContainsString('{% if not still %} data-controller="card-back"', $template);
        $this->assertStringContainsString('data-card-back-sky-value', $template);
    }

    // The controller registered under the name the component writes, its sounds and sky beside it
    public function testTheBarrelRegistersTheBackController(): void
    {
        $this->assertStringContainsString("'card-back': () => import('./js/back.js')", $this->read('assets/controllers.js'));
        $this->assertStringContainsString('from "./sky.js"', $this->read('assets/js/back.js'));
        $this->assertStringContainsString('from "./audio.js"', $this->read('assets/js/back.js'));
    }

    // The layout loads the barrel and the compiled stylesheet, which must exist
    public function testTheLayoutIsHandedTheBarrelAndTheStylesheet(): void
    {
        $provider = new AssetProvider();

        $this->assertSame(['@c975l/card-bundle/controllers.js'], $provider->getScripts());
        $this->assertSame(['bundles/c975lcard/css/styles.min.css'], $provider->getStylesheets());
        $this->assertFileExists(\dirname(__DIR__, 2) . '/public/css/styles.min.css');
    }

    private function read(string $path): string
    {
        $file = \dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileExists($file);

        return (string) file_get_contents($file);
    }
}
