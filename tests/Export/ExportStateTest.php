<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Export;

use c975L\CardBundle\Export\ExportState;
use PHPUnit\Framework\TestCase;

class ExportStateTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/card-export-state-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    // A page read again as it was gives the same fingerprint, and any change to it, to the salt or to an image it reads gives another
    public function testTheFingerprintFollowsThePageTheSaltAndTheImagesItReads(): void
    {
        $image = $this->dir . '/card.webp';
        file_put_contents($image, 'a');
        touch($image, 1000);
        $page = '<img src="file://' . $image . '">';
        $fingerprint = ExportState::fingerprint('1', $page);

        $this->assertSame($fingerprint, ExportState::fingerprint('1', $page));
        $this->assertNotSame($fingerprint, ExportState::fingerprint('2', $page));
        $this->assertNotSame($fingerprint, ExportState::fingerprint('1', $page . ' '));

        // The same name uploaded again
        file_put_contents($image, 'bb');
        touch($image, 2000);
        $this->assertNotSame($fingerprint, ExportState::fingerprint('1', $page));
    }

    // A version or a fragment after a file's path is no part of its name, the file being read all the same
    public function testAQueryOrAFragmentIsNoPartOfThePath(): void
    {
        $font = $this->dir . '/card.woff2';
        file_put_contents($font, 'a');
        touch($font, 1000);
        $page = 'url("file://' . $font . '?v=3") url("file://' . $font . '#iefix")';
        $fingerprint = ExportState::fingerprint('1', $page);

        touch($font, 2000);
        $this->assertNotSame($fingerprint, ExportState::fingerprint('1', $page));
    }

    // An item is left as it is only when nothing moved and every file it left is still there
    public function testAnItemIsFreshOnlyWithItsFingerprintAndItsFiles(): void
    {
        $state = new ExportState();
        $state->record('a.pdf', 'f1', ['a.pdf', 'a/1.jpg']);
        $all = static fn (string $output): bool => true;

        $this->assertTrue($state->isFresh('a.pdf', 'f1', $all));
        $this->assertFalse($state->isFresh('a.pdf', 'f2', $all));
        $this->assertFalse($state->isFresh('b.pdf', 'f1', $all));
        $this->assertFalse($state->isFresh('a.pdf', 'f1', static fn (string $output): bool => 'a/1.jpg' !== $output));
    }

    // An item no longer exported is forgotten and its files given back, those another item still leaves being kept
    public function testPruningGivesBackTheFilesOfTheItemsGone(): void
    {
        $state = new ExportState();
        $state->record('a', 'f', ['a.jpg', 'shared.jpg']);
        $state->record('b', 'f', ['b.jpg', 'shared.jpg']);

        $this->assertSame(['b.jpg'], $state->prune(['a']));
        $this->assertTrue($state->has('a'));
        $this->assertFalse($state->has('b'));
    }

    // The manifest is rebuilt from the items, in the order of their paths, a card gone leaving it
    public function testTheManifestIsRebuiltFromTheItems(): void
    {
        $state = new ExportState();
        $state->record('Heros/b', 'f', ['Heros/b.jpg'], 'cartes.json', ['name' => 'B']);
        $state->record('Heros/a', 'f', ['Heros/a.jpg'], 'cartes.json', ['name' => 'A']);
        $state->record('Heros/c', 'f', ['Heros/c.jpg'], 'cartes.json', ['name' => 'C']);
        $state->record('plates.pdf', 'f', ['plates.pdf']);
        $state->prune(['Heros/a', 'Heros/b', 'plates.pdf']);

        $this->assertSame(['cartes.json'], $state->manifests());
        $this->assertSame([['name' => 'A'], ['name' => 'B']], $state->manifest('cartes.json'));
    }

    // What a run saved is what the next one reads, and a file that does not read is an empty state rather than an error
    public function testTheStateReadsBackWhatWasSaved(): void
    {
        $file = $this->dir . '/state.json';
        $state = new ExportState();
        $state->record('a.pdf', 'f1', ['a.pdf']);
        $state->save($file);

        $this->assertTrue(ExportState::fromFile($file)->isFresh('a.pdf', 'f1', static fn (): bool => true));

        file_put_contents($file, '{');
        $this->assertFalse(ExportState::fromFile($file)->has('a.pdf'));
        $this->assertFalse(ExportState::fromFile($this->dir . '/none.json')->has('a.pdf'));
    }
}
