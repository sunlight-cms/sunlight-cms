<?php

if (!defined('_root')) {
    exit;
}

function _HCM_filesize($soubor = '')
{
    $soubor = _root . $soubor;

    if (file_exists($soubor)) {
        return _formatFilesize(filesize($soubor));
    }
}
