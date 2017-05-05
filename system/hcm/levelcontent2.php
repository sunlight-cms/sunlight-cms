<?php

if (!defined('_root')) {
    exit;
}

function _HCM_levelcontent2($min_uroven = 0, $max_uroven = 10000, $vyhovujici_text = "", $nevyhovujici_text = "")
{
    if (_priv_level >= (int) $min_uroven && _priv_level <= (int) $max_uroven) {
        return $vyhovujici_text;
    } else {
        return $nevyhovujici_text;
    }
}
