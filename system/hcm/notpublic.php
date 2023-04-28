<?php

use Sunlight\Hcm;
use Sunlight\User;

return function ($logged_in_content = '', $other_content = '') {
    Hcm::normalizeArgument($logged_in_content, 'string');
    Hcm::normalizeArgument($other_content, 'string');

    if (User::isLoggedIn()) {
        return $logged_in_content;
    }

    return $other_content;
};
