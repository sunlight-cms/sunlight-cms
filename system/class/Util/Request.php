<?php

namespace Sunlight\Util;

abstract class Request
{
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
