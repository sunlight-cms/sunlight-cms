<?php

return function ($path = '') {
    $path = SL_ROOT . $path;

    if (
        in_array(pathinfo($path, PATHINFO_EXTENSION), ['txt', 'htm', 'html'])
        && file_exists($path)
    ) {
        return file_get_contents($path);
    }
};
