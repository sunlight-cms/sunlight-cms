<?php

namespace Sunlight\Util;

use Kuria\RequestInfo\RequestInfo;

abstract class Request
{
    /**
     * Get current request method
     */
    static function method(): string
    {
        return RequestInfo::getMethod();
    }

    /**
     * See if a header is defined
     * 
     * @param string $name lowercase header name
     */
    static function hasHeader(string $name): bool
    {
        return isset(RequestInfo::getHeaders()[$name]);
    }

    /**
     * Get a header value
     * 
     * @param string $name lowercase header name
     */
    static function header(string $name): ?string
    {
        return RequestInfo::getHeaders()[$name] ?? null;
    }

    /**
     * Get a value from $_GET
     *
     * @param string $key key to get
     * @param mixed $default default value
     * @param bool $allow_array allow array values 1/0
     */
    static function get(string $key, $default = null, bool $allow_array = false)
    {
        if (isset($_GET[$key]) && ($allow_array || !is_array($_GET[$key]))) {
            return $_GET[$key];
        }

        return $default;
    }

    /**
     * Get a value from $_POST
     *
     * @param string $key key to get
     * @param mixed $default default value
     * @param bool $allow_array allow array values 1/0
     */
    static function post(string $key, $default = null, bool $allow_array = false)
    {
        if (isset($_POST[$key]) && ($allow_array || !is_array($_POST[$key]))) {
            return $_POST[$key];
        }

        return $default;
    }
}
