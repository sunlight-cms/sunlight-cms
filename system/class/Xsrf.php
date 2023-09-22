<?php

namespace Sunlight;

use Sunlight\Util\UrlHelper;

class Xsrf
{
    const TOKEN_NAME = '_security_token';

    /**
     * Render a hidden input with the XSRF token
     */
    static function getInput(): string
    {
        return '<input type="hidden" name="' . _e(self::TOKEN_NAME) . '" value="' . self::getToken() . '">';
    }

    /**
     * Add a XSRF parameter to a URL
     */
    static function addToUrl(string $url): string
    {
        return UrlHelper::appendParams($url, self::TOKEN_NAME . '=' . urlencode(self::getToken()));
    }

    /**
     * Get a XSRF token
     *
     * @param bool $forCheck get token for verification purposes (takes into account session ID changes) 1/0
     */
    static function getToken(bool $forCheck = false): string
    {
        static $tokens = [];

        $sessionId = $forCheck ? (Session::getPreviousId() ?? Session::getId()) : Session::getId();

        // no tokens without a session ID
        if ($sessionId === null) {
            return '';
        }

        return $tokens[$sessionId] ?? ($tokens[$sessionId] = hash_hmac('sha256', $sessionId, Core::$secret));
    }

    /**
     * Check a XSRF token
     *
     * @param bool $get check $_GET instead of $_POST 1/0
     */
    static function check(bool $get = false): bool
    {
        $token = $GLOBALS[$get ? '_GET' : '_POST'][self::TOKEN_NAME] ?? null;

        return $token === self::getToken(true);
    }
}
