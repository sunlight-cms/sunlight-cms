<?php

defined('_root') or exit;

return function ($kod = "")
{
    return "<div class='pre php-source'>" . highlight_string($kod, true) . "</div>";
};
