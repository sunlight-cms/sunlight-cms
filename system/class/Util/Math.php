<?php

namespace Sunlight\Util;

/**
 * Math helper
 */
abstract class Math
{
    /**
     * Vygenerovat nahodne cislo v danem rozmezi (inkluzivni)
     *
     * @param int $min
     * @param int $max
     * @return int
     */
    static function randomInt(int $min, int $max): int
    {
        static $fc = null;

        if ($fc === null) {
            if (function_exists('random_int')) {
                $fc = 'random_int';
            } else {
                $fc = 'mt_rand';
            }
        }

        return $fc($min, $max);
    }

    /**
     * Limit number range
     *
     * @param number      $num the number
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
