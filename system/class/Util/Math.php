<?php

namespace Sunlight\Util;

/**
 * Math helper
 */
class Math
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Limit number range
     *
     * @param number      $num the number
     * @param number|null $min minimal value or null (= unlimited)
     * @param number|null $max maximal value or null (= unlimited)
     * @return number
     */
    public static function range($num, $min, $max)
    {
        if (isset($min) && $num < $min) {
            return $min;
        }

        if (isset($max) && $num > $max) {
            return $max;
        }

        return $num;
    }
}
