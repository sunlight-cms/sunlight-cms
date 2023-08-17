<?php

namespace Sunlight\Util;

use Sunlight\Slugify\Slugify;

abstract class StringHelper
{
    /**
     * Cut a string to the specified length
     */
    static function cut(string $string, int $length): string
    {
        if (mb_strlen($string) > $length) {
            return mb_substr($string, 0, $length);
        }

        return $string;
    }

    /**
     * Cut a string to the specified length and add ellipsis
     *
     * @param string $string text to cut
     * @param int|null $length desired length
     * @param bool $convert_entities convert HTML entities to characters before cutting and back after 1/0
     */
    static function ellipsis(string $string, ?int $length, bool $convert_entities = true): string
    {
        if ($length === null || $length <= 0) {
            return $string;
        }

        if ($convert_entities) {
            $string = Html::unescape($string);
        }

        if (mb_strlen($string) > $length) {
            $string = mb_substr($string, 0, max(0, $length - 1)) . '…';
        }

        if ($convert_entities) {
            $string = _e($string);
        }

        return $string;
    }

    /**
     * Remove excess whitespace from a string
     */
    static function trimExtraWhitespace(string $string): string
    {
        return preg_replace(["{(\r\n){3,}}", '{  +}'], ["\r\n\r\n", ' '], trim($string));
    }

    /**
     * Slugify a string
     *
     * Supported options:
     * ------------------
     * - lower (1)        generate lowercase slug 1/0
     * - extra ("._")     string list of extra allowed characters
     * - max_len (255)    slug length limit or null
     * - fallback ("")    fallback slug in case the process fails
     *
     * @param array{lower?: bool, extra?: string, max_len?: int|null, fallback?: string} $options see description
     */
    static function slugify(string $input, array $options = []): string
    {
        $options += [
            'lower' => true,
            'extra' => '._',
            'max_len' => 255,
            'fallback' => '',
        ];

        $slug = Slugify::getInstance()->slugify(
            $input,
            [
                'lowercase' => $options['lower'],
                'regexp' => sprintf('{(?:[^A-Za-z0-9%s]|-)++}', preg_quote($options['extra'])),
            ]
        );

        if ($slug !== '') {
            if ($options['max_len'] !== null) {
                $slug = self::cut($slug, $options['max_len']);
            }

            return $slug;
        }

        return $options['fallback'];
    }

    /**
     * Convert a string into camel case
     */
    static function toCamelCase(string $input, bool $firstLetterLower = false): string
    {
        $output = '';
        $parts = preg_split('{[^a-zA-Z0-9\x80-\xFF]+}', $input, -1, PREG_SPLIT_NO_EMPTY);

        for ($i = 0; isset($parts[$i]); ++$i) {
            $part = mb_strtolower($parts[$i]);
            $firstLetter = mb_substr($part, 0, 1);

            if ($i > 0 || !$firstLetterLower) {
                $firstLetter = mb_strtoupper($firstLetter);
            } else {
                $firstLetter = mb_strtolower($firstLetter);
            }

            $output .= $firstLetter . mb_strtolower(mb_substr($part, 1));
        }

        return $output;
    }

    /**
     * Uppercase first letter
     */
    static function ucfirst(string $input): string
    {
        return preg_replace_callback(
            '{\p{Ll}}Au',
            function (array $match) {
                return mb_strtoupper($match[0]);
            },
            $input,
            1
        );
    }

    /**
     * Lowercase first letter (but try to preserve acronyms)
     */
    static function lcfirst(string $input): string
    {
        return preg_replace_callback(
            '{(\p{Lu})([^\p{Lu}])}Au',
            function (array $match) {
                return mb_strtolower($match[1]) . $match[2];
            },
            $input,
            1
        );
    }
}
