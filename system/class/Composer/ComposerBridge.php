<?php

namespace Sunlight\Composer;

use Sunlight\Core;
use Sunlight\Util\Filesystem;

class ComposerBridge
{
    static function clearCache()
    {
        static::initMinimalCore();

        if (Core::$cache) {
            Core::$cache->clear();
        }
    }

    static function denyAccessToVendorDirectory()
    {
        Filesystem::denyAccessToDirectory(__DIR__ . '/../../../vendor');
    }

    static function initMinimalCore()
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
