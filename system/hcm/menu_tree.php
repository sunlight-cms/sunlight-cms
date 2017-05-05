<?php

if (!defined('_root')) {
    exit;
}

function _HCM_menu_tree($od = null, $do = null, $max_hloubka = null, $class = null)
{
    _normalize($od, 'int');
    _normalize($do, 'int');
    _normalize($max_hloubka, 'int');
    _normalize($class, 'string');

    return _templateTreeMenu(array(
        'max_depth' => $max_hloubka,
        'ord_start' => $od,
        'ord_end' => $do,
        'css_class' => $class,
    ));
}
