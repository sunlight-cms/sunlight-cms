<?php

if (!defined('_root')) {
    exit;
};

return function ($kod = "")
{
    return "<div class='pre php-source'>" . highlight_string($kod, true) . "</div>";
};
