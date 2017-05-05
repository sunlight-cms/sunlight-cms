<?php

if (!defined('_root')) {
    exit;
}

function _HCM_notpublic($pro_prihlasene = "", $pro_neprihlasene = "")
{
    if (_login) {
        return $pro_prihlasene;
    } else {
        return $pro_neprihlasene;
    }
}
