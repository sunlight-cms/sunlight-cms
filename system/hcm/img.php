<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageTransformer;
use Sunlight\Router;

return function ($cesta = '', $rozmery = '', $titulek = null, $lightbox = null) {
    $cesta = SL_ROOT . $cesta;
    $thumb = ImageService::getThumbnail('hcm.img', $cesta, ImageTransformer::parseResizeOptions($rozmery));

    return "<a href='" . _e(Router::file($cesta)) . "'"
        . " target='_blank'"
        . Extend::buffer('image.lightbox', ['group' => 'hcm_img_' . ($lightbox ?? Core::$hcmUid)])
        . (($titulek != '') ? ' title=\'' . _e($titulek) . '\'' : '')
        . '>'
        . "<img src='" . _e(Router::file($thumb)) . "' alt='" . _e($titulek ?: basename($cesta)) . "'>"
        . "</a>\n";
};
