<?php

use Sunlight\Hcm;

return function ($file = '', ...$args) {
    Hcm::normalizeArgument($file, 'string');

    return _buffer(function () use ($file, $args) {
        $file = SL_ROOT . $file;

        if (file_exists($file)) {
            include $file;
        }
    });
};
