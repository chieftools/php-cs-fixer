<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ChiefTools\PhpCsFixer\EditorConfigInstaller;

class EditorConfigInstallerTest extends TestCase
{
    public function testItCopiesEditorConfigWhenMissing(): void
    {
        $projectRoot = $this->temporaryDirectory();
        $sourcePath  = $this->temporarySource('root = true');

        $status = (new EditorConfigInstaller)->install($projectRoot, $sourcePath);

        $this->assertSame(EditorConfigInstaller::COPIED, $status);
        $this->assertSame('root = true', file_get_contents($projectRoot . '/.editorconfig'));
    }

    public function testItSkipsEditorConfigWhenItAlreadyMatches(): void
    {
        $projectRoot = $this->temporaryDirectory();
        $sourcePath  = $this->temporarySource('root = true');

        file_put_contents($projectRoot . '/.editorconfig', 'root = true');

        $status = (new EditorConfigInstaller)->install($projectRoot, $sourcePath);

        $this->assertSame(EditorConfigInstaller::SKIPPED, $status);
        $this->assertSame('root = true', file_get_contents($projectRoot . '/.editorconfig'));
    }

    public function testItUpdatesEditorConfigWhenItDiffersFromThePackagedVersion(): void
    {
        $projectRoot = $this->temporaryDirectory();
        $sourcePath  = $this->temporarySource('root = true');

        file_put_contents($projectRoot . '/.editorconfig', 'root = false');

        $status = (new EditorConfigInstaller)->install($projectRoot, $sourcePath);

        $this->assertSame(EditorConfigInstaller::UPDATED, $status);
        $this->assertSame('root = true', file_get_contents($projectRoot . '/.editorconfig'));
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/chief-php-cs-fixer-' . bin2hex(random_bytes(8));

        mkdir($directory);

        return $directory;
    }

    private function temporarySource(string $contents): string
    {
        $sourcePath = $this->temporaryDirectory() . '/.editorconfig';

        file_put_contents($sourcePath, $contents);

        return $sourcePath;
    }
}
