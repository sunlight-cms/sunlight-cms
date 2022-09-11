<?php

use Sunlight\Hcm;
use Sunlight\Template;

return function ($page_id = null, $ord_start = null, $ord_end = null, $max_depth = null, $class = null) {
    Hcm::normalizeArgument($page_id, 'int');
    Hcm::normalizeArgument($ord_start, 'int');
    Hcm::normalizeArgument($ord_end, 'int');
    Hcm::normalizeArgument($max_depth, 'int');
    Hcm::normalizeArgument($class, 'string');

    return Template::treeMenu([
        'page_id' => $page_id,
        'max_depth' => $max_depth,
        'ord_start' => $ord_start,
        'ord_end' => $ord_end,
        'css_class' => $class,
    ]);
};
