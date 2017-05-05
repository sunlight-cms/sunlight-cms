<?php

if (!defined('_root')) {
    exit;
}

function _HCM_levelcontent($min_uroven = 0, $vyhovujici_text = "", $nevyhovujici_text = "")
{
    if (_priv_level >= (int) $min_uroven) {
        return $vyhovujici_text;
    } else {
        return $nevyhovujici_text;
    }
}
