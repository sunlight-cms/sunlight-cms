<?php

defined('_root') or exit;

return function ($id_stranky = null, $od = null, $do = null, $max_hloubka = null, $class = null)
{
    \Sunlight\Hcm::normalizeArgument($id_stranky, 'int');
    \Sunlight\Hcm::normalizeArgument($od, 'int');
    \Sunlight\Hcm::normalizeArgument($do, 'int');
    \Sunlight\Hcm::normalizeArgument($max_hloubka, 'int');
    \Sunlight\Hcm::normalizeArgument($class, 'string');

    return Sunlight\Template::treeMenu(array(
        'page_id' => $id_stranky,
        'max_depth' => $max_hloubka,
        'ord_start' => $od,
        'ord_end' => $do,
        'css_class' => $class,
    ));
};
