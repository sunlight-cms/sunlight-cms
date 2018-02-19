<?php

namespace Sunlight\Composer;

use Sunlight\Core;
use Sunlight\Util\Filesystem;

class ComposerBridge
{
    public static function clearCache()
    {
        static::initMinimalCore();

        if (Core::$cache) {
            Core::$cache->clear();
        }
    }

    public static function denyAccessToVendorDirectory()
    {
        Filesystem::denyAccessToDirectory(__DIR__ . '/../../../vendor');
    }

    public static function initMinimalCore()
    {
        if (!Core::isReady()) {
            $root = __DIR__ . '/../../../';

            Core::init($root, array(
                'minimal_mode' => true,
                'skip_components' => !is_dir($root . 'vendor/composer'),
                'config_file' => false,
            ));
        }
    }
}
