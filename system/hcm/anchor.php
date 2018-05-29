<?php

use Sunlight\Util\Url;

defined('_root') or exit;

return function ($nazev = '')
{
    return _e(Url::current()->path . '#' . $nazev);
};
