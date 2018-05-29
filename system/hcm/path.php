<?php

defined('_root') or exit;

return function ()
{
    return _e(Sunlight\Util\Url::current()->path);
};
