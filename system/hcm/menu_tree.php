<?php

defined('_root') or exit;

return function ($od = null, $do = null, $max_hloubka = null, $class = null)
{
    \Sunlight\Hcm::normalizeArgument($od, 'int');
    \Sunlight\Hcm::normalizeArgument($do, 'int');
    \Sunlight\Hcm::normalizeArgument($max_hloubka, 'int');
    \Sunlight\Hcm::normalizeArgument($class, 'string');

    return Sunlight\Template::treeMenu(array(
        'max_depth' => $max_hloubka,
        'ord_start' => $od,
        'ord_end' => $do,
        'css_class' => $class,
    ));
};
