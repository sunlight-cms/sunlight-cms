<?php

if (!defined('_root')) {
    exit;
}

function _HCM_iperex($odstavec = true)
{
    if ($GLOBALS['_index']['is_page'] && '' !== $GLOBALS['_page']['perex']) {
        if ($odstavec) {
            return '<p>' . $GLOBALS['_page']['perex'] . '</p>';
        }
        
        return $GLOBALS['_page']['perex'];
    }

    return '';
}
