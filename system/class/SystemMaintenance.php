<?php

namespace Sunlight;

use Sunlight\Image\ImageService;
use Sunlight\Util\Filesystem;

abstract class SystemMaintenance
{
    static function run(): void
    {
        // clean thumbnails
        ImageService::cleanThumbnails(Settings::get('thumb_cleanup_threshold'));

        // remove old files in the temporary directory
        Filesystem::purgeDirectory(
            SL_ROOT . 'system/tmp',
            true,
            function (\SplFileInfo $item, string $rootDir) {
                if (!$item->isFile()) {
                    return true; // remove dirs
                }

                if ($item->getPathname() === $rootDir . '/.gitkeep') {
                    return false; // preserve .gitkeep
                }

                return time() - $item->getMTime() > 86400; // remove files changed more than 24h ago
            }
        );

        // cleanup the logger
        Logger::cleanup();

        // cleanup the cache
        if (Core::$cache->supportsCleanup()) {
            Core::$cache->cleanup();
        }

        // check version
        VersionChecker::check();
    }
}
