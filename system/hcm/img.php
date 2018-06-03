<?php

use Sunlight\Core;
use Sunlight\Picture;
use Sunlight\Router;

defined('_root') or exit;

return function ($cesta = "", $rozmery = null, $titulek = null, $lightbox = null) {
    $cesta = _root . $cesta;

    $resize_opts = Picture::parseResizeOptions($rozmery);
    if (isset($titulek) && $titulek != "") {
        $titulek = _e($titulek);
    }
    if (!isset($lightbox)) {
        $lightbox = Core::$hcmUid;
    }

    $thumb = Picture::getThumbnail($cesta, $resize_opts);

    return "<a href='" . _e(Router::file($cesta)) . "' target='_blank' class='lightbox' data-gallery-group='lb_hcm" . _e($lightbox) . "'" . (($titulek != "") ? ' title=\'' . _e($titulek) . '\'' : '') . "><img src='" . _e(Router::file($thumb)) . "' alt='" . _e($titulek ?: basename($cesta)) . "'></a>\n";
};
