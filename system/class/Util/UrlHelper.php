<?php

namespace Sunlight\Util;

abstract class UrlHelper
{
    /**
     * Rozpoznat, zda se jedna o URL v absolutnim tvaru
     *
     * @param string $url adresa
     * @return bool
     */
    static function isAbsolute($url)
    {
        $url = @parse_url($url);

        return isset($url['scheme']);
    }

    /**
     * Overit, zda adresa neobsahuje skodlivy kod
     *
     * @param string $url adresa
     * @return bool
     */
    static function isSafe($url)
    {
        return preg_match('{[\s\0-\32a-z0-9_\-]+:}Ai', $url) === 0;
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
     * Pridat schema do URL, pokud jej neobsahuje nebo neni relativni
     *
     * @param string $url
     * @return string
     */
    static function addScheme($url)
    {
        if (mb_substr($url, 0, 7) !== 'http://' && mb_substr($url, 0, 8) !== 'https://' && $url[0] !== '/' && mb_substr($url, 0, 2) !== './') {
            $url = 'http://' . $url;
        }

        return $url;
    }
}
