<?php

namespace Sunlight\Composer;

use Sunlight\Core;
use Sunlight\Util\Filesystem;

class ComposerBridge
{
    public static function clearCache()
    {
        static::initMinimalCore();

        Core::$cache->clear();
    }

    public static function denyAccessToVendorDirectory()
    {
        Filesystem::denyAccessToDirectory(__DIR__ . '/../../../vendor');
    }

    public static function initMinimalCore()
    {
        if (!Core::isReady()) {
            Core::init(__DIR__ . '/../../../', array(
                'minimal_mode' => true,
                'config_file' => false,
            ));
        }
    }
}
