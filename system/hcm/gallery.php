<?php

use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageTransformer;
use Sunlight\Paginator;
use Sunlight\Router;

return function ($path = '', $thumbnail_size = '', $per_page = null, $lightbox = true) {
    global $_index;

    $result = '';
    $path = SL_ROOT . $path;

    if (mb_substr($path, -1, 1) != '/') {
        $path .= '/';
    }

    if (isset($per_page) && $per_page > 0) {
        $paginator = true;
        $per_page = (int) $per_page;

        if ($per_page <= 0) {
            $per_page = 1;
        }
    } else {
        $paginator = false;
    }
    
    $lightbox = (bool) $lightbox;

    $resize_opts = ImageTransformer::parseResizeOptions($thumbnail_size);

    if (file_exists($path) && is_dir($path)) {
        $handle = opendir($path);

        // load images
        $items = [];

        while (($item = readdir($handle)) !== false) {
            if ($item == '.' || $item == '..' || is_dir($item) || !ImageService::isImage($item)) {
                continue;
            }

            $items[] = $item;
        }

        closedir($handle);
        natsort($items);

        // prepare paginator
        if ($paginator) {
            $count = count($items);
            $paging = Paginator::paginate(
                $_index->url,
                $per_page,
                $count,
                [
                    'param' => 'hcm_gal' . Hcm::$uid . 'p',
                    'link_suffix' => '#hcm_gal' . Hcm::$uid,
                ]
            );
        }

        // render
        $result = '<div id="hcm_gal' . Hcm::$uid . "\" class=\"gallery\">\n";
        $counter = 0;

        foreach ($items as $item) {
            if ($paginator && $counter > $paging['last']) {
                break;
            }

            if (!$paginator || Paginator::isItemInRange($paging, $counter)) {
                $thumb = ImageService::getThumbnail('gallery', $path . $item, $resize_opts);
                $result .= '<a href="' . _e(Router::file($path . $item)) . '" target="_blank"' . ($lightbox ? Extend::buffer('image.lightbox', ['group' => 'hcm_gal_' . Hcm::$uid]) : '') . '>'
                    . '<img src="' . _e(Router::file($thumb)) . '" alt="' . _e($item) . '">'
                    . "</a>\n";
            }

            $counter++;
        }

        $result .= "</div>\n";

        if ($paginator) {
            $result .= $paging['paging'];
        }
    }

    return $result;
};
