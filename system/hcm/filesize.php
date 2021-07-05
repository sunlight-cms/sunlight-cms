<?php

use Sunlight\GenericTemplates;

return function ($soubor = '') {
    $soubor = SL_ROOT . $soubor;

    if (file_exists($soubor)) {
        return GenericTemplates::renderFileSize(filesize($soubor));
    }
};
