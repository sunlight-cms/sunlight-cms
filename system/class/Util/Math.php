<?php

namespace Sunlight\Util;

/**
 * Math helper
 */
abstract class Math
{
    /**
     * Limit number range
     *
     * @param number $num the number
     * @param number|null $min minimum value or null (= unlimited)
     * @param number|null $max maximum value or null (= unlimited)
     * @return number
     */
    static function range($num, $min, $max)
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
