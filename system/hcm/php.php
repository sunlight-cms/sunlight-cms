<?php

return function ($kod = "", $ze_souboru = false) {
    return _buffer(function () use ($kod, $ze_souboru) {
        if ($ze_souboru) {
            // ze souboru
            $soubor = SL_ROOT . $kod;
            if (file_exists($soubor)) {
                $_params = array_slice(func_get_args(), 2);

                include $soubor;
            }
        } else {
            // kod
            eval($kod);
        }
    });
};
