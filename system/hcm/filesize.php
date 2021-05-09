<?php

use Sunlight\GenericTemplates;

return function ($soubor = '') {
    $soubor = _root . $soubor;

    if (file_exists($soubor)) {
        return GenericTemplates::renderFileSize(filesize($soubor));
    }
};
