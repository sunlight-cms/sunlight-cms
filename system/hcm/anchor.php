<?php

use Sunlight\Util\Url;

if (!defined('_root')) {
    exit;
}

function _HCM_anchor($nazev = '')
{
    return _e(Url::current()->path . '#' . $nazev);
}
