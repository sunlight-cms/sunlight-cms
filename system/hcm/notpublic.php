<?php

use Sunlight\User;

return function ($pro_prihlasene = '', $pro_neprihlasene = '') {
    if (User::isLoggedIn()) {
        return $pro_prihlasene;
    }

    return $pro_neprihlasene;
};
