<?php

defined('_root') or exit;

return function ($soubor = '')
{
    $soubor = _root . $soubor;

    if (file_exists($soubor)) {
        return \Sunlight\Generic::renderFileSize(filesize($soubor));
    }
};
