<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageTransformer;
use Sunlight\Paginator;
use Sunlight\Router;

return function ($cesta = '', $rozmery = '', $strankovani = null, $lightbox = true) {
    global $_index;

    // priprava
    $result = '';
    $cesta = SL_ROOT . $cesta;
    if (mb_substr($cesta, -1, 1) != '/') {
        $cesta .= '/';
    }
    if (isset($strankovani) && $strankovani > 0) {
        $strankovat = true;
        $strankovani = (int) $strankovani;
        if ($strankovani <= 0) {
            $strankovani = 1;
        }
    } else {
        $strankovat = false;
    }
    
    $lightbox = (bool) $lightbox;

    $resize_opts = ImageTransformer::parseResizeOptions($rozmery);

    if (file_exists($cesta) && is_dir($cesta)) {
        $handle = opendir($cesta);

        // nacteni polozek
        $items = [];
        while (($item = readdir($handle)) !== false) {
            if ($item == '.' || $item == '..' || is_dir($item) || !ImageService::isImage($item)) {
                continue;
            }
            $items[] = $item;
        }
        closedir($handle);
        natsort($items);

        // priprava strankovani
        if ($strankovat) {
            $count = count($items);
            $paging = Paginator::render($_index->url, $strankovani, $count, '', '#hcm_gal' . Core::$hcmUid, 'hcm_gal' . Core::$hcmUid . 'p');
        }

        // vypis
        $result = "<div id='hcm_gal" . Core::$hcmUid . "' class='gallery'>\n";
        $counter = 0;
        foreach ($items as $item) {
            if ($strankovat && $counter > $paging['last']) {
                break;
            }
            if (!$strankovat || Paginator::isItemInRange($paging, $counter)) {
                $thumb = ImageService::getThumbnail('gallery', $cesta . $item, $resize_opts);
                $result .= "<a href='" . _e(Router::file($cesta . $item)) . "' target='_blank'" . ($lightbox ? Extend::buffer('image.lightbox', ['group' => 'hcm_gal_' . Core::$hcmUid]) : '') . "><img src='" . _e(Router::file($thumb)) . "' alt='" . _e($item) . "'></a>\n";
            }
            $counter++;
        }
        $result .= "</div>\n";
        if ($strankovat) {
            $result .= $paging['paging'];
        }

    }

    return $result;
};
