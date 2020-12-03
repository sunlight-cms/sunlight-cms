<?php

defined('_root') or exit;

return function ($soubor = '') {
    $soubor = _root . $soubor;

    if (
        in_array(pathinfo($soubor, PATHINFO_EXTENSION), ['txt', 'htm', 'html'])
        && file_exists($soubor)
    ) {
        return file_get_contents($soubor);
    }
};
