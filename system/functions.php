<?php

use Sunlight\Core;

/**
 * Bufferovat a vratit vystup daneho callbacku
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
 * Prevest HTML znaky na entity
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
 * Ziskat preklad
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
