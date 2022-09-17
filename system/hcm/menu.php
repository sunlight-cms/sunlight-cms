<?php

use Sunlight\Hcm;
use Sunlight\Template;

return function ($ord_start = null, $ord_end = null, $class = null) {
    Hcm::normalizeArgument($ord_start, 'int');
    Hcm::normalizeArgument($ord_end, 'int');

    return Template::menu($ord_start, $ord_end, $class);
};
