<?php

if (!defined('_root')) {
    exit;
}

function _HCM_path()
{
    return _e(Sunlight\Util\Url::current()->path);
}
