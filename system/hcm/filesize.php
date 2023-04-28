<?php

use Sunlight\GenericTemplates;
use Sunlight\Hcm;

return function ($path = '') {
    Hcm::normalizePathArgument($path, true);

    if ($path !== null) {
        return GenericTemplates::renderFileSize((int) filesize($path));
    }
};
