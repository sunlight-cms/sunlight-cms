<?php

return function ($code = '', $from_file = false) {
    return _buffer(function () use ($code, $from_file) {
        if ($from_file) {
            $soubor = SL_ROOT . $code;

            if (file_exists($soubor)) {
                $_params = array_slice(func_get_args(), 2);

                include $soubor;
            }
        } else {
            eval($code);
        }
    });
};
