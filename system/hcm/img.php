<?php

use Sunlight\Core;

defined('_root') or exit;

return function ($cesta = "", $rozmery = null, $titulek = null, $lightbox = null)
{
    $cesta = _root . $cesta;

    $resize_opts = _pictureResizeOptions($rozmery);
    if (isset($titulek) && $titulek != "") {
        $titulek = _e($titulek);
    }
    if (!isset($lightbox)) {
        $lightbox = Core::$hcmUid;
    }

    $thumb = _pictureThumb($cesta, $resize_opts);

    return "<a href='" . _e(_linkFile($cesta)) . "' target='_blank' class='lightbox' data-gallery-group='lb_hcm" . _e($lightbox) . "'" . (($titulek != "") ? ' title=\'' . _e($titulek) . '\'' : '') . "><img src='" . _e(_linkFile($thumb)) . "' alt='" . _e($titulek ?: basename($cesta)) . "'></a>\n";
};
