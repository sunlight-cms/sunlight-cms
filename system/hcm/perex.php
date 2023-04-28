<?php

use Sunlight\Hcm;
use Sunlight\Template;

return function ($paragraph = true) {
    Hcm::normalizeArgument($paragraph, 'bool');

    if (Template::currentIsPage() && $GLOBALS['_page']['perex'] !== '') {
        if ($paragraph) {
            return '<p>' . $GLOBALS['_page']['perex'] . '</p>';
        }
        
        return $GLOBALS['_page']['perex'];
    }

    return '';
};
