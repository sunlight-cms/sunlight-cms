<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Image\ImageFormat;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageTransformer;
use Sunlight\Router;

defined('_root') or exit;

return function ($cesta = "", $typ = 'text', $pocet = 1, $rozmery_nahledu = null) {
    $result = "";
    $cesta = _root . $cesta;
    if (mb_substr($cesta, -1, 1) != "/") {
        $cesta .= "/";
    }
    $pocet = (int) $pocet;

    if (file_exists($cesta) && is_dir($cesta)) {
        $handle = opendir($cesta);


        switch ($typ) {
            case 'image':
            case 2:
                $extension_filter = function ($ext) { return ImageFormat::isValidFormat($ext); };
                $resize_opts = ImageTransformer::parseResizeOptions($rozmery_nahledu);
                break;
            case 'text':
            default:
                $extension_filter = function ($ext) { return $ext === 'txt' || $ext === 'htm' || $ext === 'html'; };
                break;
        }

        $items = [];
        while (($item = readdir($handle)) !== false) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (is_dir($cesta . $item) || $item == "." || $item == ".." || !$extension_filter($ext)) {
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
                $randitems = [$randitems];
            }

            foreach ($randitems as $item) {
                $item = $items[$item];
                switch ($typ) {
                    case 2:
                        $thumb = ImageService::getThumbnail('hcm.randomfile', $cesta . $item, $resize_opts);
                        $result .= "<a href='" . _e(Router::file($cesta . $item)) . "'"
                            . " target='_blank'"
                            . Extend::buffer('image.lightbox', ['group' => 'hcm_rnd_' . Core::$hcmUid])
                            . '>'
                            . "<img src='" . _e(Router::file($thumb)) . "' alt='" . _e($item) . "'>"
                            . "</a>\n";
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
};
