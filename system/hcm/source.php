<?php

use Sunlight\Hcm;

return function ($code = '') {
    Hcm::normalizeArgument($code, 'string');

    return '<div class="pre">' . nl2br(_e(trim($code)), false) . '</div>';
};
