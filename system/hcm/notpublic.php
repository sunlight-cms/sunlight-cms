<?php

defined('_root') or exit;

return function ($pro_prihlasene = "", $pro_neprihlasene = "") {
    if (_logged_in) {
        return $pro_prihlasene;
    } else {
        return $pro_neprihlasene;
    }
};
