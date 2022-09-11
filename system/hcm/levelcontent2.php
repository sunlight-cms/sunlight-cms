<?php

use Sunlight\User;

return function ($min_level = 0, $max_level = User::MAX_LEVEL, $privileged_content = '', $other_content = '') {
    if (User::getLevel() >= (int) $min_level && User::getLevel() <= (int) $max_level) {
        return $privileged_content;
    }

    return $other_content;
};
