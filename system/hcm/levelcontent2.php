<?php

use Sunlight\Hcm;
use Sunlight\User;

return function ($min_level = 0, $max_level = User::MAX_LEVEL, $privileged_content = '', $other_content = '') {
    Hcm::normalizeArgument($min_level, 'int');
    Hcm::normalizeArgument($max_level, 'int');
    Hcm::normalizeArgument($privileged_content, 'string');
    Hcm::normalizeArgument($other_content, 'string');

    if (User::getLevel() >= $min_level && User::getLevel() <= $max_level) {
        return $privileged_content;
    }

    return $other_content;
};
