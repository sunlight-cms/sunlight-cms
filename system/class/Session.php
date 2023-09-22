<?php

namespace Sunlight;

abstract class Session
{
    /** @var string */
    static $cookieName = 'sl_session';
    /** @var bool|null */
    private static $enabled;
    /** @var string|null */
    private static $previousId;

    static function init(bool $enabled): void
    {
        if (self::$enabled !== null) {
            throw new \LogicException('Already initialized');
        }

        if ($enabled) {
            // cookie parameters
            $cookieParams = [
                'lifetime' => 0,
                'path' => Core::getBaseUrl()->getPath() . '/',
                'domain' => '',
                'secure' => Core::isHttpsEnabled(),
                'httponly' => true,
                'samesite' => 'Lax',
            ];

            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params($cookieParams);
            } else {
                session_set_cookie_params(
                    $cookieParams['lifetime'],
                    $cookieParams['path'],
                    $cookieParams['domain'],
                    $cookieParams['secure'],
                    $cookieParams['httponly']
                );
            }

            // set session name and start it
            session_name(self::$cookieName);
            session_start();
        } else {
            // no session
            $_SESSION = [];
        }

        self::$enabled = $enabled;
    }

    /**
     * See if sessions are enabled
     */
    static function isEnabled(): bool
    {
        return self::$enabled === true;
    }

    /**
     * Move current session data to a new session ID
     */
    static function regenerate(): void
    {
        if (self::isEnabled()) {
            $currentData = $_SESSION;
            $_SESSION = [];
            session_regenerate_id();
            $_SESSION = $currentData;
        }
    }

    /**
     * Destroy the current session data and create a new session ID
     */
    static function destroy(): void
    {
        if (self::isEnabled()) {
            $_SESSION = [];
            session_regenerate_id();
        }
    }

    /**
     * End the current session and write data
     */
    static function close(): void
    {
        if (self::isEnabled()) {
            session_write_close();
        }
    }

    /**
     * Get current session ID
     */
    static function getId(): ?string
    {
        if (self::isEnabled()) {
            $id = session_id();

            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * Get previous session ID
     */
    static function getPreviousId(): ?string
    {
        return self::$previousId;
    }
}
