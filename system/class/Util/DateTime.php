<?php

namespace Sunlight\Util;

abstract class DateTime
{
    /**
     * Formatovat cas jako HTTP-date
     *
     * @param int  $time     timestamp
     * @param bool $relative relativne k aktualnimu casu 1/0
     * @return string
     */
    static function formatForHttp($time, $relative = false)
    {
        if ($relative) {
            $time += time();
        }

        return gmdate('D, d M Y H:i:s', $time) . ' GMT';
    }

    /**
     * Zjistit zda je den podle casu vychozu a zapadu slunce
     *
     * @param int|null $time      timestamp nebo null (= aktualni)
     * @param bool     $get_times navratit casy misto vyhodnoceni, ve formatu array(time, sunrise, sunset)
     * @return bool|array
     */
    static function isDayTime($time = null, $get_times = false)
    {
        // priprava casu
        if (!isset($time)) {
            $time = time();
        }
        $sunrise = date_sunrise($time, SUNFUNCS_RET_TIMESTAMP, _geo_latitude, _geo_longitude, _geo_zenith, date('Z') / 3600);
        $sunset = date_sunset($time, SUNFUNCS_RET_TIMESTAMP, _geo_latitude, _geo_longitude, _geo_zenith, date('Z') / 3600);

        // navrat vysledku
        if ($get_times) {
            return array($time, $sunrise, $sunset);
        }

        if ($time >= $sunrise && $time < $sunset) {
            return true;
        }

        return false;
    }
}
