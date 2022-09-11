<?php

namespace Sunlight\Util;

use Kuria\Url\Url;
use Sunlight\Core;

abstract class UrlHelper
{
    /**
     * Detect an absolute URL
     *
     * A URL is considered absolute if it starts with a "/" or contains a scheme.
     */
    static function isAbsolute(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        return $url[0] === '/' || preg_match('{\w+://}A', $url);
    }

    /**
     * See if a URL is safe
     *
     * Safe URLs: URLs without scheme, HTTP/HTTPS URLs
     * Unsafe URLs: non-HTTP schemes, "data:", "javascript:" etc.
     */
    static function isSafe(string $url): bool
    {
        return preg_match('{https?://}Ai', $url) || !preg_match('{[\s\0-\32a-z0-9_\-]+:}Ai', $url);
    }

    /**
     * Append a query string to a URL
     */
    static function appendParams(string $url, string $params): string
    {
        if ($params === '') {
            return $url;
        }

        return $url
            . (strpos($url, '?') === false ? '?' : '&')
            . $params;
    }

    /**
     * Add HTTP scheme to a URL if it doesn't have any and is not relative to current host
     */
    static function addScheme(string $url): string
    {
        if (
            $url !== ''
            && $url[0] !== '/'
            && strncmp($url, 'http://', 7) !== 0
            && strncmp($url, 'https://', 8) !== 0
            && strncmp($url, './', 2) !== 0
        ) {
            $url = 'http://' . $url;
        }

        return $url;
    }

    /**
     * Add or update scheme in absolute URL
     *
     * If the system uses HTTPS but the URL is HTTP, it will be converted to HTTPS.
     */
    static function ensureValidScheme(string $url): string
    {
        if ($url === '' || $url[0] === '/' || strncmp($url, './', 2) === 0) {
            // relative URL
            return $url;
        }
    
        $parsedUrl = Url::parse($url);
        $baseScheme = Core::getBaseUrl()->getScheme();
        
        if (!$parsedUrl->hasScheme()) {
            // absolute URL with no scheme
            return $baseScheme . '://' . $url;
        }

        if ($baseScheme === 'https' && $parsedUrl->getScheme() !== $baseScheme) {
            // http => https
            $parsedUrl->setScheme($baseScheme);
        }

        return $parsedUrl->buildAbsolute();
    }
}
