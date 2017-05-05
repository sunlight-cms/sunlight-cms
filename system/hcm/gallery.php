<?php

if (!defined('_root')) {
    exit;
}

function _HCM_gallery($cesta = "", $rozmery = null, $strankovani = null, $lightbox = true)
{
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

    $resize_opts = _pictureResizeOptions($rozmery);

    if (file_exists($cesta) && is_dir($cesta)) {
        $handle = opendir($cesta);

        // nacteni polozek
        $items = array();
        while (false !== ($item = readdir($handle))) {
            $ext = pathinfo($item);
            if (isset($ext['extension'])) {
                $ext = mb_strtolower($ext['extension']);
            } else {
                $ext = "";
            }
            if (is_dir($item) || $item == "." || $item == ".." || !in_array($ext, Sunlight\Core::$imageExt)) {
                continue;
            }
            $items[] = $item;
        }
        closedir($handle);
        natsort($items);

        // priprava strankovani
        if ($strankovat) {
            $count = count($items);
            $paging = _resultPaging($_index['url'], $strankovani, $count, "", "#hcm_gal" . Sunlight\Core::$hcmUid, "hcm_gal" . Sunlight\Core::$hcmUid . "p");
        }

        // vypis
        $result = "<div id='hcm_gal" . Sunlight\Core::$hcmUid . "' class='gallery'>\n";
        $counter = 0;
        foreach ($items as $item) {
            if ($strankovat && $counter > $paging['last']) {
                break;
            }
            if (!$strankovat || ($strankovat && _resultPagingIsItemInRange($paging, $counter))) {
                $thumb = _pictureThumb($cesta . $item, $resize_opts);
                $result .= "<a href='" . _e(_linkFile($cesta . $item)) . "' target='_blank'" . ($lightbox ? " class='lightbox' data-gallery-group='lb_hcm" . Sunlight\Core::$hcmUid . "'" : '') . "><img src='" . _e(_linkFile($thumb)) . "' alt='" . _e($item) . "'></a>\n";
            }
            $counter++;
        }
        $result .= "</div>\n";
        if ($strankovat) {
            $result .= $paging['paging'];
        }

    }

    return $result;
}
