<?php

if (!defined('_root')) {
    exit;
};

return function ($kod = "")
{
    return "<div class='pre'>" . nl2br(_e(trim($kod)), false) . "</div>";
};
