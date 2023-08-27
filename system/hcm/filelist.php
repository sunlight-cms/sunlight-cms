<?php

use Sunlight\GenericTemplates;
use Sunlight\Hcm;
use Sunlight\Router;
use Sunlight\Util\Filesystem;

return function ($path = '', $show_file_size = false) {
    Hcm::normalizePathArgument($path, false);
    Hcm::normalizeArgument($path, 'string');
    Hcm::normalizeArgument($show_file_size, 'bool');

    if ($path === null || !is_dir($path)) {
        return '';
    }

    $items = [];

    foreach (Filesystem::createIterator($path) as $item) {
        if ($item->isFile() && Filesystem::isSafeFile($item->getFilename())) {
            $items[$item->getFilename()] = $item;
        }
    }

    ksort($items, SORT_NATURAL);

    $output = "<ul class=\"filelist\">\n";

    if (!empty($items)) {
        foreach ($items as $item) {
            $output .= '<li>'
                . '<a href="' . _e(Router::file($item->getPathname())) . '" target="_blank">'
                . _e($item->getFilename())
                . '</a>'
                . ($show_file_size ? sprintf(' <span class="filesize">(%s)</span>', GenericTemplates::renderFilesize((int) $item->getSize())) : '')
                . "</li>\n";
        }
    } else {
        $output .= '<li>' . _lang('global.nokit') . "</li>\n";
    }

    $output .= "</ul>\n";

    return $output;
};
