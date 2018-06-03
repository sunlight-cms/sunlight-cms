<?php

use Sunlight\Util\Url;

defined('_root') or exit;

return function () {
    return _e(Url::current()->path);
};
