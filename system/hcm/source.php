<?php

use Sunlight\Hcm;

return function ($content = '', $class = null) {
    Hcm::normalizeArgument($content, 'string');
    Hcm::normalizeArgument($class, 'string', true);

    return '<pre'
        . ($class !== null ? ' class="' . _e($class) . '"' : '')
        . '>'
        . _e(trim($content)) 
        . '</pre>';
};
