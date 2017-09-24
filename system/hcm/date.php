<?php

if (!defined('_root')) {
    exit;
}

function _HCM_date($format = _time_format, $time = null)
{
    return date($format, $time !== null ? $time : time());
}
