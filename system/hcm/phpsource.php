<?php

if (!defined('_root')) {
    exit;
}

function _HCM_phpsource($kod = "")
{
    return "<div class='pre php-source'>" . highlight_string($kod, true) . "</div>";
}
