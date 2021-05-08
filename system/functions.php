<?php

use Sunlight\Core;

/**
 * Buffer and return output of the given callback
 *
 * @param callable $callback
 * @param array    $arguments
 * @return string
 */
function _buffer(callable $callback, array $arguments = []): string
{
    ob_start();

    try {
        call_user_func_array($callback, $arguments);
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }

    return ob_get_clean();
}

/**
 * Convert special HTML characters to entities
 *
 * @param string $input        vstupni retezec
 * @param bool   $doubleEncode prevadet i jiz existujici entity 1/0
 * @return string
 */
function _e(string $input, bool $doubleEncode = true): string
{
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8', $doubleEncode);
}

/**
 * Get a translation
 *
 * @param string      $key
 * @param array|null  $replacements
 * @param string|null $fallback
 * @return string
 */
function _lang(string $key, ?array $replacements = null, ?string $fallback = null): string
{
    return Core::$lang->get($key, $replacements, $fallback);
}
