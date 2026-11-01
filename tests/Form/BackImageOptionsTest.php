<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Form;

use c975L\CardBundle\Controller\Management\CardBackCrudController;
use c975L\CardBundle\Controller\Management\CardBackPictureCrudController;
use c975L\CardBundle\Form\BackImageOptions;
use c975L\UiBundle\Form\VichImageOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

// A back takes an image and nothing else, judged by the real validator on the bytes of real files: the size alone used to be checked, whatever the file
class BackImageOptionsTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function testAnImageIsTaken(): void
    {
        $this->assertSame(0, $this->violations((string) base64_decode(self::PNG, true)));
    }

    public function testAScriptOrADocumentIsRefused(): void
    {
        foreach (['<?php echo 1;', '<html><script>alert(1)</script></html>', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', "%PDF-1.4\n"] as $content) {
            $this->assertSame(1, $this->violations($content));
        }
    }

    // On top of UiBundle's own: whatever it checks on an image field, a name's extension among them, a back goes on checking
    public function testUiBundlesOwnConstraintsStay(): void
    {
        foreach (VichImageOptions::default('10M')['constraints'] as $constraint) {
            $this->assertContainsEquals($constraint, BackImageOptions::default()['constraints']);
        }
    }

    // Both upload fields go through these options. Read where they are written, the fields being built with services this test does not need
    public function testBothFieldsUseThem(): void
    {
        foreach ([CardBackCrudController::class, CardBackPictureCrudController::class] as $controller) {
            $source = (string) file_get_contents((string) new \ReflectionClass($controller)->getFileName());

            $this->assertStringContainsString('->setFormTypeOptions(BackImageOptions::default())', $source);
        }
    }

    private function violations(string $content): int
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'back');
        file_put_contents($path, $content);
        $count = Validation::createValidator()->validate($path, BackImageOptions::default()['constraints'])->count();
        unlink($path);

        return $count;
    }
}
