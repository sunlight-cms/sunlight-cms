<?php

if (!defined('_root')) {
    exit;
}

function _HCM_source($kod = "")
{
    return "<div class='pre'>" . nl2br(_e(trim($kod)), false) . "</div>";
}
