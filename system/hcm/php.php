<?php

return function ($code = '', $from_file = false) {
    return _buffer(function () use ($code, $from_file) {
        if ($from_file) {
            $file = SL_ROOT . $code;

            if (file_exists($file)) {
                $_params = array_slice(func_get_args(), 2);

                include $file;
            }
        } else {
            eval($code);
        }
    });
};
