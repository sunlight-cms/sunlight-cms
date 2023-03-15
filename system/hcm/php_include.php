<?php

return function ($file = '', ...$args) {


    return _buffer(function () use ($file, $args) {
        $file = SL_ROOT . $file;

        if (file_exists($file)) {
            include $file;
        }
    });
};
