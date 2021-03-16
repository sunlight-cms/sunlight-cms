<?php

namespace Sunlight\Util;

abstract class ArgList
{
    private const KEYWORD_MAP = ['null' => true, 'true' => true, 'false' => true];
    private const KEYWORD_VALUES = ['null' => null, 'true' => true, 'false' => false];

    /**
     * Parse an argument list
     *
     * @param string $input
     * @return array
     */
    static function parse(string $input): array
    {
        $args = [];

        $length = strlen($input);
        $offset = 0;

        while (
            $offset < $length
            && preg_match(
                <<<'REGEX'
{(?>\s*)(?:"(?:(?P<d>[^"\\]*(?:\\.[^"\\]*)*))"|'(?P<s>(?:[^'\\]*(?:\\.[^'\\]*)*))'|(?P<p>[^,]*?))(?>\s*)(?:,|$)}A
REGEX
                ,
                $input,
                $match,
                0,
                $offset
            )
        ) {
            $offset += strlen($match[0]);

            if (isset($match['p'])) {
                if (isset(self::KEYWORD_MAP[$match['p']])) {
                    // keyword
                    $args[] = self::KEYWORD_VALUES[$match['p']];
                } else {
                    // plain value
                    $args[] = $match['p'];
                }
            } else {
                // quoted string
                $args[] = stripcslashes($match[isset($match['s']) ? 's' : 'd']);
            }
        }

        return $args;
    }
}
