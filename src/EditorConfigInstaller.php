<?php

namespace ChiefTools\PhpCsFixer;

use RuntimeException;

class EditorConfigInstaller
{
    public const COPIED  = 'copied';
    public const SKIPPED = 'skipped';
    public const UPDATED = 'updated';

    public function install(string $projectRoot, ?string $sourcePath = null): string
    {
        $sourcePath ??= dirname(__DIR__) . '/.editorconfig';

        if (!is_file($sourcePath)) {
            throw new RuntimeException("Unable to find source .editorconfig at [{$sourcePath}].");
        }

        $sourceContents = file_get_contents($sourcePath);

        if ($sourceContents === false) {
            throw new RuntimeException("Unable to read source .editorconfig at [{$sourcePath}].");
        }

        $targetPath = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.editorconfig';

        $targetExists = is_file($targetPath);

        if ($targetExists && file_get_contents($targetPath) === $sourceContents) {
            return self::SKIPPED;
        }

        if (file_put_contents($targetPath, $sourceContents) === false) {
            throw new RuntimeException("Unable to write .editorconfig to [{$targetPath}].");
        }

        return $targetExists ? self::UPDATED : self::COPIED;
    }
}
