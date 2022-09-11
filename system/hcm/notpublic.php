<?php

use Sunlight\User;

return function ($logged_in_content = '', $other_content = '') {
    if (User::isLoggedIn()) {
        return $logged_in_content;
    }

    return $other_content;
};
