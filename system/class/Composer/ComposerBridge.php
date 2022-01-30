<?php

namespace Sunlight\Composer;

use Sunlight\Util\Filesystem;

class ComposerBridge
{
    static function clearCache(): void
    {
        Filesystem::purgeDirectory(__DIR__ . '/../../cache', ['keep_dir' => true]);
        @touch(__DIR__ . '/../../cache/.gitkeep');
    }

    static function denyAccessToVendorDirectory(): void
    {
        Filesystem::denyAccessToDirectory(__DIR__ . '/../../../vendor');
    }
}
