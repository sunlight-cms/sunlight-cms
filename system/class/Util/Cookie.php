<?php

namespace Sunlight\Util;

use Sunlight\Core;

abstract class Cookie
{
    static function exists(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    static function get(string $name): ?string
    {
        $value = $_COOKIE[$name] ?? null;

        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Set a cookie
     *
     * Note: samesite is only supported on PHP 7.3 or newer
     *
     * @param array{
     *     expires?: int,
     *     path?: string,
     *     domain?: string,
     *     secure?: bool,
     *     httponly?: bool,
     *     samesite?: string,
     * } $options
     */
    static function set(string $name, string $value, array $options = []): void
    {
        $options += [
            'expires' => 0,
            'path' => Core::getBaseUrl()->getPath() . '/',
            'domain' => '',
            'secure' => Core::isHttpsEnabled(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $value, $options);
        } else {
            setcookie(
                $name,
                $value,
                $options['expires'],
                $options['path'],
                $options['domain'],
                $options['secure'],
                $options['httponly']
            );
        }
    }

    /**
     * Remove a cookie
     *
     * Should use the same options as when the cookie was set with {@see set()}
     */
    static function remove(string $name, array $options = []): void
    {
        self::set($name, '', ['expires' => time() - 3600] + $options);
    }
}
