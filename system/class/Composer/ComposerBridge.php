<?php

namespace Sunlight\Composer;

use Sunlight\Core;
use Sunlight\Util\Filesystem;

class ComposerBridge
{
    static function clearCache(): void
    {
        self::initMinimalCore();

        if (Core::$cache) {
            Core::$cache->clear();
        }
    }

    static function denyAccessToVendorDirectory(): void
    {
        Filesystem::denyAccessToDirectory(__DIR__ . '/../../../vendor');
    }

    static function initMinimalCore(): void
    {
        if (!Core::isReady()) {
            Core::init('./', [
                'minimal_mode' => true,
                'skip_components' => !is_dir('./vendor/composer'),
                'config_file' => false,
            ]);
        }
    }
}
