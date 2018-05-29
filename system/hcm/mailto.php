<?php

if (!defined('_root')) {
    exit;
};

return function ($email = "")
{
    return _mailto($email);
};
