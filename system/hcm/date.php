<?php

use Sunlight\Settings;

return function ($format = null, $time = null) {
    if ($time === null) {
        $time = time();
    } elseif (ctype_digit($time) || is_int($time)) {
        $time = (int) $time;
    } else {
        $time = strtotime($time);
    }

    return date($format ?? Settings::get('time_format'), $time);
};
