<?php

use Sunlight\Hcm;

return function ($file = '', ...$args) {
    Hcm::normalizePathArgument($file, true, true);

    if ($file === null) {
        return '';
    }

    return _buffer(function () use ($file, $args) {
        include $file;
    });
};
