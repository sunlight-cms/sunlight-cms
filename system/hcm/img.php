<?php

use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageTransformer;
use Sunlight\Router;

return function ($path = '', $thumbnail_size = '', $title = null, $lightbox = null) {
    Hcm::normalizePathArgument($path, true);
    Hcm::normalizeArgument($thumbnail_size, 'string');
    Hcm::normalizeArgument($title, 'string', true);
    Hcm::normalizeArgument($lightbox, 'string', true);

    if ($path === null) {
        return '';
    }

    $thumb = ImageService::getThumbnail('hcm.img', $path, ImageTransformer::parseResizeOptions($thumbnail_size));

    return '<a href="' . _e(Router::file($path)) . '"'
        . ' target="_blank"'
        . Extend::buffer('image.lightbox', ['group' => 'hcm_img_' . ($lightbox ?? Hcm::$uid)])
        . (($title !== null) ? ' title=\'' . _e($title) . '\'' : '')
        . '>'
        . '<img src="' . _e(Router::file($thumb)) . '" alt="' . _e($title ?: basename($path)) . '">'
        . "</a>\n";
};
