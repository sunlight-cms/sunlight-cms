<?php

defined('_root') or exit;

return function ($od = null, $do = null, $class = null)
{
    \Sunlight\Hcm::normalizeArgument($od, 'int');
    \Sunlight\Hcm::normalizeArgument($do, 'int');

    return Sunlight\Template::menu($od, $do, $class);
};
