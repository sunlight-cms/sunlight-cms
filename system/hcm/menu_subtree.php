<?php

use Sunlight\Hcm;
use Sunlight\Template;

return function ($id_stranky = null, $od = null, $do = null, $max_hloubka = null, $class = null) {
    Hcm::normalizeArgument($id_stranky, 'int');
    Hcm::normalizeArgument($od, 'int');
    Hcm::normalizeArgument($do, 'int');
    Hcm::normalizeArgument($max_hloubka, 'int');
    Hcm::normalizeArgument($class, 'string');

    return Template::treeMenu([
        'page_id' => $id_stranky,
        'max_depth' => $max_hloubka,
        'ord_start' => $od,
        'ord_end' => $do,
        'css_class' => $class,
    ]);
};
