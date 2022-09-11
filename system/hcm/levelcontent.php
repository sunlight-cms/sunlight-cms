<?php

use Sunlight\User;

return function ($min_level = 0, $privileged_content = '', $other_content = '') {
    if (User::getLevel() >= (int) $min_level) {
        return $privileged_content;
    }

    return $other_content;
};
