<?php

use Sunlight\GenericTemplates;

return function ($path = '') {
    $path = SL_ROOT . $path;

    if (file_exists($path)) {
        return GenericTemplates::renderFileSize(filesize($path));
    }
};
