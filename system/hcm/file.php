<?php

return function ($soubor = '') {
    $soubor = SL_ROOT . $soubor;

    if (
        in_array(pathinfo($soubor, PATHINFO_EXTENSION), ['txt', 'htm', 'html'])
        && file_exists($soubor)
    ) {
        return file_get_contents($soubor);
    }
};
