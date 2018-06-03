<?php

use Sunlight\GenericTemplates;

defined('_root') or exit;

return function ($soubor = '') {
    $soubor = _root . $soubor;

    if (file_exists($soubor)) {
        return GenericTemplates::renderFileSize(filesize($soubor));
    }
};
