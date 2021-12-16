<?php

use Sunlight\Hcm;
use Sunlight\Router;

return function ($path = '') {
    Hcm::normalizeArgument($path, 'string', false);

    return Router::path((string) $path);
};
