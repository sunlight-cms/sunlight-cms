<?php

use Sunlight\Hcm;
use Sunlight\Template;

return function ($page_id = -1, $ord_start = null, $ord_end = null, $max_depth = null, $class = null) {
    Hcm::normalizeArgument($page_id, 'int', true);
    Hcm::normalizeArgument($ord_start, 'int', true);
    Hcm::normalizeArgument($ord_end, 'int', true);
    Hcm::normalizeArgument($max_depth, 'int', true);
    Hcm::normalizeArgument($class, 'string', true);

    return Template::treeMenu([
        'page_id' => $page_id,
        'max_depth' => $max_depth,
        'ord_start' => $ord_start,
        'ord_end' => $ord_end,
        'css_class' => $class,
    ]);
};
