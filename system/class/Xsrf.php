<?php

namespace Sunlight;

use Sunlight\Util\UrlHelper;

class Xsrf
{
    /**
     * Render a hidden input with the XSRF token
     */
    static function getInput(): string
    {
        return '<input type="hidden" name="_security_token" value="' . self::getToken() . '">';
    }

    /**
     * Add a XSRF parameter to a URL
     */
    static function addToUrl(string $url): string
    {
        return UrlHelper::appendParams($url, '_security_token=' . urlencode(self::getToken()));
    }

    /**
     * Get a XSRF token
     *
     * @param bool $forCheck token je ziskavan pro kontrolu (je bran ohled na situaci, ze mohlo zrovna dojit ke zmene ID session) 1/0
     */
    static function getToken(bool $forCheck = false): string
    {
        // token cache
        static $tokens = [null, null];

        // token type - current or for verification purposes
        $type = ($forCheck ? 1 : 0);

        // generate a token
        if ($tokens[$type] === null) {
            // determine session ID
            if (!Core::$sessionEnabled) {
                $sessionId = 'none';
            } elseif ($forCheck && Core::$sessionRegenerate) {
                // session has just been regenerated, use previous ID
                $sessionId = Core::$sessionPreviousId;
            } else {
                // current session ID
                $sessionId = session_id();
                if ($sessionId === '') {
                    $sessionId = 'none';
                }
            }

            // generate token
            $tokens[$type] = hash_hmac('sha256', $sessionId, Core::$secret);
        }

        return $tokens[$type];
    }

    /**
     * Check a XSRF token
     *
     * @param bool $get check $_GET instead of $_POST 1/0
     */
    static function check(bool $get = false): bool
    {
        $token = $GLOBALS[$get ? '_GET' : '_POST']['_security_token'] ?? null;

        return $token === self::getToken(true);
    }
}
