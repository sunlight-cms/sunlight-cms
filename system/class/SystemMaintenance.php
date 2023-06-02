<?php

namespace Sunlight;

use Sunlight\Image\ImageService;
use Sunlight\Util\Filesystem;

abstract class SystemMaintenance
{
    static function run(): void
    {
        $startTime = time();

        // clean thumbnails
        ImageService::cleanThumbnails(Settings::get('thumb_cleanup_threshold'));

        // remove old files in the temporary directory
        Filesystem::purgeDirectory(SL_ROOT . 'system/tmp', [
            'keep_dir' => true,
            'files_only' => true,
            'file_callback' => function (\SplFileInfo $file) {
                return
                    substr($file->getFilename(), 0, 1) !== '.' // not a hidden file
                    && time() - $file->getMTime() > 86400; // changed more than 24h ago
            },
        ]);

        // cleanup the logger
        Logger::cleanup();

        // cleanup the cache
        if (Core::$cache->supportsCleanup()) {
            Core::$cache->cleanup();
        }

        // check version
        VersionChecker::check();

        Logger::info('system', sprintf('Finished system maintenance in %d seconds', time() - $startTime));
    }
}
