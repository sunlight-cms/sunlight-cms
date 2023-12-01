<?php

namespace Sunlight\Composer;

use Sunlight\Util\Filesystem;

class ComposerBridge
{
    static function clearCache(): void
    {
        Filesystem::emptyDirectory(__DIR__ . '/../../cache', function (\SplFileInfo $item) {
            return $item->isDir() || $item->getFilename() !== '.gitkeep';
        });
    }

    static function updateDirectoryAccess(): void
    {
        $root = __DIR__ . '/../../../';

        foreach (['vendor', 'bin', '.git'] as $dir) {
            if (is_dir($root . $dir)) {
                Filesystem::denyAccessToDirectory($root . $dir);
            }
        }
    }
}
