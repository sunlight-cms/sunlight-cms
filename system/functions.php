<?php

use Sunlight\Core;

/**
 * Buffer and return output of the given callback
 */
function _buffer(callable $callback, array $arguments = []): string
{
    ob_start();

    try {
        $callback(...$arguments);
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }

    return ob_get_clean();
}

/**
 * Convert special HTML characters to entities
 *
 * @param string $input input string
 * @param bool $doubleEncode encode existing entities as well 1/0
 */
function _e(string $input, bool $doubleEncode = true): string
{
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8', $doubleEncode);
}

/**
 * Get a translation
 */
function _lang(string $key, ?array $replacements = null, ?string $fallback = null): string
{
    return Core::$dictionary->get($key, $replacements, $fallback);
}

/**
 * Format a number
 */
function _num($number, int $decimals = 2): string
{
    if (!is_numeric($number)) {
        return '-';
    }

    if (is_int($number) || $decimals <= 0 || abs(fmod($number, 1)) < 0.1 ** $decimals) {
        // an integer value
        return Core::$langPlugin->formatInteger((int) $number);
    }

    // a float value
    return Core::$langPlugin->formatFloat((float) $number, $decimals);
}
