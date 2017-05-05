<?php

if (!defined('_root')) {
    exit;
}

function _HCM_randomfile($cesta = "", $typ = 1, $pocet = 1, $rozmery_nahledu = null)
{
    $result = "";
    $cesta = _root . $cesta;
    if (mb_substr($cesta, -1, 1) != "/") {
        $cesta .= "/";
    }
    $pocet = (int) $pocet;

    if (file_exists($cesta) && is_dir($cesta)) {
        $handle = opendir($cesta);

        switch ($typ) {
            case 2:
                $allowed_extensions = Sunlight\Core::$imageExt;
                $resize_opts = _pictureResizeOptions($rozmery_nahledu);
                break;
            default:
                $allowed_extensions = array("txt", "htm", "html");
                break;
        }

        $items = array();
        while (false !== ($item = readdir($handle))) {
            $ext = pathinfo($item);
            if (isset($ext['extension'])) {
                $ext = mb_strtolower($ext['extension']);
            } else {
                $ext = "";
            }
            if (is_dir($cesta . $item) || $item == "." || $item == ".." || !in_array($ext, $allowed_extensions)) {
                continue;
            }
            $items[] = $item;
        }

        if (count($items) != 0) {
            if ($pocet > count($items)) {
                $pocet = count($items);
            }
            $randitems = array_rand($items, $pocet);
            if (!is_array($randitems)) {
                $randitems = array($randitems);
            }

            foreach ($randitems as $item) {
                $item = $items[$item];
                switch ($typ) {
                    case 2:
                        $thumb = _pictureThumb($cesta . $item, $resize_opts);
                        $result .= "<a href='" . _e(_linkFile($cesta . $item)) . "' target='_blank' class='lightbox' data-gallery-group='lb_hcm" . Sunlight\Core::$hcmUid . "'><img src='" . _e(_linkFile($thumb)) . "' alt='" . _e($item) . "'></a>\n";
                        break;
                    default:
                        $result .= file_get_contents($cesta . $item);
                        break;
                }
            }
        }

        closedir($handle);
    }

    return $result;
}
