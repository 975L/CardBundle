<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Export;

use c975L\CardBundle\Export\PageRenderer;
use PHPUnit\Framework\TestCase;

class PageRendererTest extends TestCase
{
    // The print blocks go, nested rules and all, the screen rules around them staying
    public function testThePrintRulesAreDropped(): void
    {
        $css = 'a{color:red}@media print{* {color:black !important} @page{size:A4}}b{color:blue}@media print{c{d:e}}';

        $this->assertSame('a{color:red}b{color:blue}', PageRenderer::withoutPrintRules($css));
    }

    // The site's paths are put back onto the disk, in the page and in its stylesheets, an offscreen image loaded all the same
    public function testThePathsArePutBackOntoTheDisk(): void
    {
        $project = sys_get_temp_dir() . '/card-renderer-' . bin2hex(random_bytes(4));
        mkdir($project . '/public/css', 0o775, true);
        file_put_contents($project . '/public/css/site.css', 'a{background:url("/medias/a.webp")}@media print{a{color:black}}');
        $renderer = new PageRenderer($project);

        $this->assertSame('<img src="file://' . $project . '/public/medias/a.webp" loading="eager">', $renderer->onDisk('<img src="/medias/a.webp" loading="lazy">'));
        $this->assertSame('a{background:url("file://' . $project . '/public/medias/a.webp")}' . "\n", $renderer->stylesheets(['public/css/site.css', 'public/css/missing.css']));
        $this->assertStringContainsString('@media print', $renderer->stylesheets(['public/css/site.css'], false));

        // A protocol-relative url points at another host, not at the site
        file_put_contents($project . '/public/css/site.css', 'a{background:url(//cdn.example/x.woff2)}');
        $this->assertSame('a{background:url(//cdn.example/x.woff2)}' . "\n", $renderer->stylesheets(['public/css/site.css']));
        $this->assertSame('<img src="//cdn.example/a.webp">', $renderer->onDisk('<img src="//cdn.example/a.webp">'));

        // An AssetMapper file is read from its source, its digest holding a dash or not
        mkdir($project . '/assets/images', 0o775, true);
        file_put_contents($project . '/assets/images/end-en.webp', 'webp');
        foreach (['end-en-2ucDF-q.webp', 'end-en-2ucDFkq.webp'] as $digested) {
            $this->assertSame('<img src="file://' . $project . '/assets/images/end-en.webp">', $renderer->onDisk('<img src="/assets/images/' . $digested . '">'));
        }
        unlink($project . '/assets/images/end-en.webp');
        rmdir($project . '/assets/images');
        rmdir($project . '/assets');

        unlink($project . '/public/css/site.css');
        rmdir($project . '/public/css');
        rmdir($project . '/public');
        rmdir($project);
    }
}
