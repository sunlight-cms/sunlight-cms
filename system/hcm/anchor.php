<?php

use Sunlight\Util\Url;

if (!defined('_root')) {
    exit;
};

return function ($nazev = '')
{
    return _e(Url::current()->path . '#' . $nazev);
};
