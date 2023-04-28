<?php

use Sunlight\Hcm;
use Sunlight\Settings;

return function ($format = null, $time = null) {
    Hcm::normalizeArgument($format, 'string', true);

    if ($time === null) {
        $time = time();
    } elseif (ctype_digit($time) || is_int($time)) {
        $time = (int) $time;
    } else {
        $time = strtotime((string) $time);

        if ($time === false) {
            return '';
        }
    }

    return date($format ?? Settings::get('time_format'), $time);
};
