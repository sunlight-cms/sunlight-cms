<?php

namespace Sunlight\Util;

abstract class Html
{
    /**
     * Convert HTML entities back to normal characters
     */
    static function unescape(string $input): string
    {
        static $map = null;

        if ($map === null) {
            $map = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES));
        }

        return strtr($input, $map);
    }

    /**
     * Cut text that may include HTML entities to the desired length
     */
    static function cut(string $html, int $length): string
    {
        if ($length > 0 && mb_strlen($html) > $length) {
            return self::fixTrailingHtmlEntity(mb_substr($html, 0, $length));
        }

        return $html;
    }

    /**
     * Remove incomplete HTML entity from the end of a string
     */
    static function fixTrailingHtmlEntity(string $string): string
    {
        return preg_replace('{\\s*&[^;]*$}D', '', $string);
    }
}
