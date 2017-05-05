<?php

if (!defined('_root')) {
    exit;
}

function _HCM_menu($od = null, $do = null, $class = null)
{
    _normalize($od, 'int');
    _normalize($do, 'int');

    return _templateMenu($od, $do, $class);
}
