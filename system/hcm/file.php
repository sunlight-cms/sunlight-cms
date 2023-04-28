<?php

use Sunlight\Hcm;

return function ($path = '') {
    Hcm::normalizePathArgument($path, true);

    if ($path !== null) {
        return (string) file_get_contents($path);
    }
};
