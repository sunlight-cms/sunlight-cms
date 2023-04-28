<?php

return function ($key = '', ...$args) {
    if ($args) {
        $replacements = [];
        $argCount = count($args);

        for ($i = 0; $i < $argCount; $i += 2) {
            $replacements[$args[$i]] = (string) ($args[$i + 1] ?? '');
        }
    } else {
        $replacements = null;
    }

    return _lang($key, $replacements);
};
