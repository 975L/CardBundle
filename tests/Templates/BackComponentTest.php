<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Templates;

use c975L\CardBundle\Payment\BackBasketItemProvider;
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
        $this->assertStringContainsString('{% if not still and not plain %} data-controller="card-back{{ controller ? \' \' ~ controller }}"', $template);
        $this->assertStringContainsString("'square' == format ? ' card-back--square'", $template);
    }

    // Plain, the shape alone: neither picture nor rule, nor any controller
    public function testAPlainBackKeepsTheShapeAlone(): void
    {
        $template = $this->read('templates/components/Back.html.twig');

        $this->assertMatchesRegularExpression('/\{% if not plain %\}.*card-back__picture.*card-back__rule.*\{% endif %\}\s*\{% block content %\}/s', $template);
        $this->assertStringContainsString('.card-back--plain', $this->read('sass/_back.scss'));
    }

    // A held back posts what the header says, a purchase goes to the basket under the kind the provider answers to, in the shop's currency unless named
    public function testTheChoicePostsItsBackAndSellsItThroughTheBasket(): void
    {
        $choice = $this->read('templates/components/BackChoice.html.twig');

        $this->assertStringContainsString('name="back" value="{{ choice.slug }}"', $choice);
        $this->assertStringContainsString('name="_token"', $choice);
        $this->assertStringContainsString("csrf_token('submit')", $choice);
        $this->assertStringContainsString("format_currency(currency ?: config('shop-currency') ?: 'EUR')", $choice);
        $this->assertStringContainsString('data-type="' . BackBasketItemProvider::KIND . '"', $this->read('templates/components/_back_buy.html.twig'));
    }

    // The controller registered under the name the component writes, the canvas left for a site's own to extend
    public function testTheBarrelRegistersTheBackController(): void
    {
        $this->assertStringContainsString("'card-back': () => import('./js/back.js')", $this->read('assets/controllers.js'));
        $this->assertStringContainsString('paint(context, time)', $this->read('assets/js/back-canvas.js'));
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
