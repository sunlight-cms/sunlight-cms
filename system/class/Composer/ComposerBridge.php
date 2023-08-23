<?php

namespace Sunlight\Composer;

use Sunlight\Util\Filesystem;

class ComposerBridge
{
    static function clearCache(): void
    {
        foreach (Filesystem::createIterator(__DIR__ . '/../../cache') as $item) {
            if ($item->isDir()) {
                if (!Filesystem::purgeDirectory($item->getPathname(), ['keep_dir' => true], $failedPath)) {
                    throw new \RuntimeException(sprintf('Could not delete "%s"', $failedPath));
                }
            } elseif ($item->getFilename() !== '.gitkeep') {
                unlink($item->getPathname());
            }
        }
    }

    static function denyAccessToVendorDirectory(): void
    {
        Filesystem::denyAccessToDirectory(__DIR__ . '/../../../vendor');

        if (is_dir(__DIR__ . '/../../../bin')) {
            Filesystem::denyAccessToDirectory(__DIR__ . '/../../../bin');
        }
    }
}
