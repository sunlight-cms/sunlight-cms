<?php

if (!defined('_root')) {
    exit;
};

return function ($soubor = '')
{
    $soubor = _root . $soubor;

    if (file_exists($soubor)) {
        return _formatFilesize(filesize($soubor));
    }
};
