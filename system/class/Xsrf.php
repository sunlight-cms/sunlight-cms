<?php

namespace Sunlight;

use Sunlight\Util\UrlHelper;

class Xsrf
{
    /**
     * Sestavit kod skryteho inputu pro XSRF ochranu
     *
     * @return string
     */
    static function getInput(): string
    {
        return '<input type="hidden" name="_security_token" value="' . self::getToken() . '">';
    }

    /**
     * Pridat XSRF parametr do URL
     *
     * @param string $url adresa
     * @return string
     */
    static function addToUrl(string $url): string
    {
        return UrlHelper::appendParams($url, '_security_token=' . urlencode(self::getToken()));
    }

    /**
     * Vygenerovat XSRF token
     *
     * @param bool $forCheck token je ziskavan pro kontrolu (je bran ohled na situaci, ze mohlo zrovna dojit ke zmene ID session) 1/0
     * @return string
     */
    static function getToken(bool $forCheck = false): string
    {
        // cache tokenu
        static $tokens = [null, null];

        // typ tokenu (aktualni ci pro kontrolu)
        $type = ($forCheck ? 1 : 0);

        // vygenerovat token
        if ($tokens[$type] === null) {

            // zjistit ID session
            if (!Core::$sessionEnabled) {
                // session je deaktivovana
                $sessionId = 'none';
            } elseif ($forCheck && Core::$sessionRegenerate) {
                // ID session bylo prave pregenerovane
                $sessionId = Core::$sessionPreviousId;
            } else {
                // ID aktualni session
                $sessionId = session_id();
                if ($sessionId === '') {
                    $sessionId = 'none';
                }
            }

            // vygenerovat token
            $tokens[$type] = hash_hmac('sha256', $sessionId, Core::$secret);

        }

        // vystup
        return $tokens[$type];
    }

    /**
     * Zkontrolovat XSRF token
     *
     * @param bool $get zkontrolovat token v $_GET namisto $_POST 1/0
     * @return bool
     */
    static function check(bool $get = false): bool
    {
        // determine data source variable
        if ($get) {
            $tvar = '_GET';
        } else {
            $tvar = '_POST';
        }

        // load used token
        if (isset($GLOBALS[$tvar]['_security_token'])) {
            $test = strval($GLOBALS[$tvar]['_security_token']);
            unset($GLOBALS[$tvar]['_security_token']);
        } else {
            $test = null;
        }

        // check
        if ($test !== null && self::getToken(true) === $test) {
            return true;
        }

        return false;
    }
}
