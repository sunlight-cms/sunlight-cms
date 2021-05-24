<?php

namespace Sunlight\Util;

use Kuria\Url\Url;
use Sunlight\Core;

abstract class UrlHelper
{
    /**
     * Rozpoznat, zda se jedna o URL v absolutnim tvaru, tj. obsahuje schema nebo zacina "/"
     *
     * @param string $url adresa
     * @return bool
     */
    static function isAbsolute(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        return $url[0] === '/' || preg_match('{\w+://}A', $url);
    }

    /**
     * Overit, zda adresa neobsahuje skodlivy kod
     *
     * @param string $url adresa
     * @return bool
     */
    static function isSafe(string $url): bool
    {
        return preg_match('{https?://}Ai', $url) || !preg_match('{[\s\0-\32a-z0-9_\-]+:}Ai', $url);
    }

    /**
     * Vlozeni GET promenne do odkazu
     *
     * @param string $url    adresa
     * @param string $params cisty query retezec
     * @return string
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
     * Pridat HTTP schema do URL, pokud jej neobsahuje a neni relativni
     *
     * @param string $url
     * @return string
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
     * Pridat/zmenit schema v absolutni URL, pokud jej neobsahuje nebo neni HTTPS (pouziva-li web HTTPS)
     *
     * @param string $url
     * @return string
     */
    static function ensureValidScheme(string $url): string
    {
        if ($url === '' || $url[0] === '/' || strncmp($url, './', 2) === 0) {
            // relativni URL
            return $url;
        }
    
        $parsedUrl = Url::parse($url);
        $baseScheme = Core::getBaseUrl()->getScheme();
        
        if (!$parsedUrl->hasScheme()) {
            // absolutni URL bez schematu
            return $baseScheme . '://' . $url;
        }

        if ($baseScheme === 'https' && $parsedUrl->getScheme() !== $baseScheme) {
            // http => https
            $parsedUrl->setScheme($baseScheme);
        }

        return $parsedUrl->buildAbsolute();
    }
}
