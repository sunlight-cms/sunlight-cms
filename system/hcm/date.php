<?php

if (!defined('_root')) {
    exit;
};

return function ($format = _time_format, $time = null)
{
    if ($time === null) {
        $time = time();
    } elseif (ctype_digit($time) || is_int($time)) {
        $time = (int) $time;
    } else {
        $time = strtotime($time);
    }

    return date($format, $time);
};
