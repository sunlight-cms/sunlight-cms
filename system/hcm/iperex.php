<?php

use Sunlight\Template;

return function ($odstavec = true) {
    if (Template::currentIsPage() && $GLOBALS['_page']['perex'] !== '') {
        if ($odstavec) {
            return '<p>' . $GLOBALS['_page']['perex'] . '</p>';
        }
        
        return $GLOBALS['_page']['perex'];
    }

    return '';
};
