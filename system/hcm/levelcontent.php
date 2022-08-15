<?php

use Sunlight\User;

return function ($min_uroven = 0, $vyhovujici_text = '', $nevyhovujici_text = '') {
    if (User::getLevel() >= (int) $min_uroven) {
        return $vyhovujici_text;
    }

    return $nevyhovujici_text;
};
