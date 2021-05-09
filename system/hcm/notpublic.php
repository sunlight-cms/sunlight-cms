<?php

return function ($pro_prihlasene = "", $pro_neprihlasene = "") {
    if (_logged_in) {
        return $pro_prihlasene;
    }

    return $pro_neprihlasene;
};
