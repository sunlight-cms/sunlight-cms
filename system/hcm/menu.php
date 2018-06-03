<?php

use Sunlight\Hcm;
use Sunlight\Template;

defined('_root') or exit;

return function ($od = null, $do = null, $class = null) {
    Hcm::normalizeArgument($od, 'int');
    Hcm::normalizeArgument($do, 'int');

    return Template::menu($od, $do, $class);
};
