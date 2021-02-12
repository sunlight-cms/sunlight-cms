<?php

use Sunlight\Template;

defined('_root') or exit;

return function ($odstavec = true) {
    if (Template::currentIsPage() && $GLOBALS['_page']['perex'] !== '') {
        if ($odstavec) {
            return '<p>' . $GLOBALS['_page']['perex'] . '</p>';
        }
        
        return $GLOBALS['_page']['perex'];
    }

    return '';
};
