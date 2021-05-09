<?php

return function ($min_uroven = 0, $max_uroven = _priv_max_level, $vyhovujici_text = "", $nevyhovujici_text = "") {
    if (_priv_level >= (int) $min_uroven && _priv_level <= (int) $max_uroven) {
        return $vyhovujici_text;
    }

    return $nevyhovujici_text;
};
