<?php

use Sunlight\Hcm;
use Sunlight\Template;

defined('_root') or exit;

return function ($od = null, $do = null, $max_hloubka = null, $class = null)
{
    Hcm::normalizeArgument($od, 'int');
    Hcm::normalizeArgument($do, 'int');
    Hcm::normalizeArgument($max_hloubka, 'int');
    Hcm::normalizeArgument($class, 'string');

    return Template::treeMenu(array(
        'max_depth' => $max_hloubka,
        'ord_start' => $od,
        'ord_end' => $do,
        'css_class' => $class,
    ));
};
