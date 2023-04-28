<?php

use Sunlight\Hcm;

return function ($php_code = '') {
    Hcm::normalizeArgument($php_code, 'string');

    return '<div class="pre php-source">' . highlight_string($php_code, true) . '</div>';
};
