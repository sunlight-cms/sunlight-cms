<?php

if (!defined('_root')) {
    exit;
}

function _HCM_anchor($nazev = '')
{
    return _e(Sunlight\Util\Url::current()->path . '#' . $nazev);
}
