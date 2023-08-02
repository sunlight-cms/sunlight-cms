<?php

use Sunlight\Hcm;

return function ($php_code = '', $class = null) {
    Hcm::normalizeArgument($php_code, 'string');
    Hcm::normalizeArgument($class, 'string', true);

    return '<div class="pre php-source' . ($class !== null ? ' ' . _e($class) : '') . '">' 
        . highlight_string(trim($php_code), true) 
        . '</div>';
};
