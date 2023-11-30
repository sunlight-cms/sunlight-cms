<?php

namespace Sunlight\Util;

abstract class HtmlFilter
{
    /** @var \HTMLPurifier|null */
    private static $purifier;

    static function sanitize(string $html): string
    {
        return self::getPurifier()->purify($html);
    }

    private static function getPurifier(): \HTMLPurifier
    {
        return self::$purifier ?? (self::$purifier = self::createPurifier());
    }

    private static function createPurifier(): \HTMLPurifier
    {
        $cacheDir = SL_ROOT . 'system/cache/html_purifier';
        Filesystem::ensureDirectoryExists($cacheDir, true);

        $config = \HTMLPurifier_HTML5Config::createDefault();
        $config->set('Cache.SerializerPath', $cacheDir);
        
        return new \HTMLPurifier($config);
    }
}
