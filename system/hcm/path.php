<?php

use Sunlight\Hcm;
use Sunlight\Router;

return function ($path = '') {
    Hcm::normalizeArgument($path, 'string');

    return Router::path($path);
};
