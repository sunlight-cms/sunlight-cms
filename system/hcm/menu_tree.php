<?php

use Sunlight\Hcm;
use Sunlight\Template;

return function ($ord_start = null, $ord_end = null, $max_depth = null, $class = null) {
    Hcm::normalizeArgument($ord_start, 'int', true);
    Hcm::normalizeArgument($ord_end, 'int', true);
    Hcm::normalizeArgument($max_depth, 'int', true);
    Hcm::normalizeArgument($class, 'string', true);

    return Template::treeMenu([
        'max_depth' => $max_depth,
        'ord_start' => $ord_start,
        'ord_end' => $ord_end,
        'css_class' => $class,
    ]);
};
