<?php

use Sunlight\Core;
use Sunlight\Paginator;
use Sunlight\Picture;
use Sunlight\Router;

defined('_root') or exit;

return function ($cesta = "", $rozmery = null, $strankovani = null, $lightbox = true) {
    global $_index;

    // priprava
    $result = "";
    $cesta = _root . $cesta;
    if (mb_substr($cesta, -1, 1) != "/") {
        $cesta .= "/";
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

    $resize_opts = Picture::parseResizeOptions($rozmery ?? "?x128");

    if (file_exists($cesta) && is_dir($cesta)) {
        $handle = opendir($cesta);

        // nacteni polozek
        $items = [];
        while (($item = readdir($handle)) !== false) {
            $ext = pathinfo($item);
            if (isset($ext['extension'])) {
                $ext = mb_strtolower($ext['extension']);
            } else {
                $ext = "";
            }
            if (is_dir($item) || $item == "." || $item == ".." || !in_array($ext, Core::$imageExt)) {
                continue;
            }
            $items[] = $item;
        }
        closedir($handle);
        natsort($items);

        // priprava strankovani
        if ($strankovat) {
            $count = count($items);
            $paging = Paginator::render($_index['url'], $strankovani, $count, "", "#hcm_gal" . Core::$hcmUid, "hcm_gal" . Core::$hcmUid . "p");
        }

        // vypis
        $result = "<div id='hcm_gal" . Core::$hcmUid . "' class='gallery'>\n";
        $counter = 0;
        foreach ($items as $item) {
            if ($strankovat && $counter > $paging['last']) {
                break;
            }
            if (!$strankovat || ($strankovat && Paginator::isItemInRange($paging, $counter))) {
                $thumb = Picture::getThumbnail($cesta . $item, $resize_opts);
                $result .= "<a href='" . _e(Router::file($cesta . $item)) . "' target='_blank'" . ($lightbox ? " class='lightbox' data-gallery-group='lb_hcm" . Core::$hcmUid . "'" : '') . "><img src='" . _e(Router::file($thumb)) . "' alt='" . _e($item) . "'></a>\n";
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
