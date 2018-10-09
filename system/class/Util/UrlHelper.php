<?php

namespace Sunlight\Util;

abstract class UrlHelper
{
    /**
     * Rozpoznat, zda se jedna o URL v absolutnim tvaru, tj. obsahuje schema nebo zacina "/"
     *
     * @param string $url adresa
     * @return bool
     */
    static function isAbsolute($url)
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
    static function isSafe($url)
    {
        return preg_match('{https?://}Ai', $url) || !preg_match('{[\s\0-\32a-z0-9_\-]+:}Ai', $url);
    }

    /**
     * Vlozeni GET promenne do odkazu
     *
     * @param string $url    adresa
     * @param string $params cisty query retezec
     * @param bool   $entity pouzit &amp; pro oddeleni 1/0
     * @return string
     */
    static function appendParams($url, $params, $entity = true)
    {
        // oddelovaci znak
        if ($params !== '') {
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                if ($entity) {
                    $url .= '&amp;';
                } else {
                    $url .= '&';
                }
            }
        }

        return $url . ($entity ? _e($params) : $params);
    }

    /**
     * Pridat HTTP schema do URL, pokud jej neobsahuje a neni relativni
     *
     * @param string $url
     * @return string
     */
    static function addScheme($url)
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
     */
    static function ensureValidScheme($url)
    {
        if ($url === '' || $url[0] === '/' || strncmp($url, './', 2) === 0) {
            // relativni URL
            return $url;
        }
    
        $parsedUrl = Url::parse($url);
        $baseScheme = Url::base()->scheme;
        
        if ($parsedUrl->scheme === null) {
            // absolutni URL bez schematu
            return $baseScheme . '://' . $url;
        }

        if ($baseScheme === 'https' && $parsedUrl->scheme !== $baseScheme) {
            // http => https
            $parsedUrl->scheme = $baseScheme;
        }

        return $parsedUrl->generateAbsolute();
    }
}
