<?php

defined('_root') or exit;

return function ($kod = "", $ze_souboru = false)
{
    return _buffer(function () use ($kod, $ze_souboru) {
        if ($ze_souboru) {
            // ze souboru
            $soubor = _root . $kod;
            if (file_exists($soubor)) {
                $_params = func_get_args();
                array_slice($_params, 2);

                include $soubor;
            }
        } else {
            // kod
            eval($kod);
        }
    });
};
