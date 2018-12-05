<?php

namespace Sunlight\Util;

abstract class ArgList
{
    protected static $keywordMap = array('null' => true, 'true' => true, 'false' => true);
    protected static $keywordValues = array('null' => null, 'true' => true, 'false' => false);

    /**
     * Parse an argument list
     *
     * @param string $input
     * @return array
     */
    static function parse($input)
    {
        $args = array();

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
                if (isset(static::$keywordMap[$match['p']])) {
                    // keyword
                    $args[] = static::$keywordValues[$match['p']];
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
