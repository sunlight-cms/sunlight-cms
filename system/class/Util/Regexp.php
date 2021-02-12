<?php

namespace Sunlight\Util;

/**
 * Regexp helper
 */
abstract class Regexp
{
    /**
     * Replace substrings using a regular expression
     *
     * Advantages over preg_replace() and preg_replace_callback():
     *
     *      - supports initial offset
     *      - tracks offset and passes it to the callback
     *
     * Structure of the matches array passed to the callback:
     *
     *      array(
     *          0 => array('complete match', offset),
     *          1 => array('matching group 1', offset),
     *          ...
     *      )
     *
     * @param string   $pattern  regexp pattern to search for
     * @param string   $subject  string to search in
     * @param callback $callback replacement callback(array matches, int offset): string
     * @param int      $limit    maximum number of replacements, -1 = unlimited
     * @param int      $offset   initial matching offset
     * @param int|null &$count   variable to store the number of replacements into
     * @return string|bool false on failure
     */
    static function replace(string $pattern, string $subject, callable $callback, int $limit = -1, int $offset = 0, ?int &$count = null)
    {
        $count = 0;
        $matches = null;
        $output = '';

        // add initial offset part to the output
        if ($offset > 0) {
            $output .= substr($subject, 0, $offset);
        }

        // match the subject
        while (
            (-1 === $limit || $count < $limit)
            && ($result = preg_match($pattern, $subject, $matches, PREG_OFFSET_CAPTURE, $offset)) !== 0
        ) {
            // evaluate the result
            if ($result === false) {
                return false;
            }
            if ($result !== 1) {
                break;
            }

            // increment match counter
            ++$count;

            // append data between matches to the output
            if ($matches[0][1] > $offset) {
                $output .= substr($subject, $offset, $matches[0][1] - $offset);
            }

            // invoke the callback
            $output .= call_user_func($callback, $matches, $matches[0][1]);

            // calculate new offset
            $offset = $matches[0][1] + strlen($matches[0][0]);
        }
        
        // handle no matches
        if ($offset === 0) {
            return $subject;
        }

        // append rest of the subject
        if ($offset < strlen($subject)) {
            $output .= substr($subject, $offset);
        }

        return $output;
    }
}
