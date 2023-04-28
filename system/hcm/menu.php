<?php

use Sunlight\Hcm;
use Sunlight\Template;

return function ($ord_start = null, $ord_end = null, $class = null) {
    Hcm::normalizeArgument($ord_start, 'int', true);
    Hcm::normalizeArgument($ord_end, 'int', true);
    Hcm::normalizeArgument($class, 'string', true);

    return Template::menu($ord_start, $ord_end, $class);
};
