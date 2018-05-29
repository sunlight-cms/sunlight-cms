<?php

if (!defined('_root')) {
    exit;
};

return function ($odstavec = true)
{
    if ($GLOBALS['_index']['is_page'] && $GLOBALS['_page']['perex'] !== '') {
        if ($odstavec) {
            return '<p>' . $GLOBALS['_page']['perex'] . '</p>';
        }
        
        return $GLOBALS['_page']['perex'];
    }

    return '';
};
