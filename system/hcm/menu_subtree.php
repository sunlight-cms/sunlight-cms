<?php

defined('_root') or exit;

return function ($id_stranky = null, $od = null, $do = null, $max_hloubka = null, $class = null)
{
    _normalize($id_stranky, 'int');
    _normalize($od, 'int');
    _normalize($do, 'int');
    _normalize($max_hloubka, 'int');
    _normalize($class, 'string');

    return \Sunlight\Template::treeMenu(array(
        'page_id' => $id_stranky,
        'max_depth' => $max_hloubka,
        'ord_start' => $od,
        'ord_end' => $do,
        'css_class' => $class,
    ));
};
