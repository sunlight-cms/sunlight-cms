<?php

if (!defined('_root')) {
    exit;
};

return function ()
{
    return _e(Sunlight\Util\Url::current()->path);
};
