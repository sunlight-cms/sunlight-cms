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
        $config->set('Attr.AllowedRel', [
            'alternate' => true,
            'author' => true,
            'bookmark' => true,
            'external' => true,
            'help' => true,
            'license' => true,
            'next' => true,
            'nofollow' => true,
            'noopener' => true,
            'noreferrer' => true,
            'prev' => true,
            'search' => true,
            'tag' => true,
        ]);
        $config->set('Attr.AllowedFrameTargets', [
            '_blank' => true,
            '_self' => true,
            '_parent' => true,
            '_top' => true,
        ]);
        $config->set('HTML.TargetNoreferrer', false);
        $config->set('HTML.TargetNoopener', false);
        
        return new \HTMLPurifier($config);
    }
}
