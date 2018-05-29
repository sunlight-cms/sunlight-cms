<?php

defined('_root') or exit;

return function ($od = null, $do = null, $class = null)
{
    _normalize($od, 'int');
    _normalize($do, 'int');

    return \Sunlight\Template::menu($od, $do, $class);
};
