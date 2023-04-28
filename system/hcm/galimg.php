<?php

use Sunlight\Database\Database as DB;
use Sunlight\Gallery;
use Sunlight\Hcm;
use Sunlight\Image\ImageTransformer;

return function ($gallery = null, $type = 'new', $thumbnail_size = null, $limit = null) {
    Hcm::normalizeArgument($gallery, 'string', true);
    Hcm::normalizeArgument($type, 'string');
    Hcm::normalizeArgument($thumbnail_size, 'string', true);
    Hcm::normalizeArgument($limit, 'int', true);

    if ($gallery !== null && !empty($gallery = explode('-', $gallery))) {
        $home_cond = 'home IN(' . DB::arr($gallery) . ')';
    } else {
        $home_cond = '1';
    }

    if ($limit !== null) {
        $limit = abs($limit);
    } else {
        $limit = 1;
    }

    // size
    if ($thumbnail_size !== null) {
        $resize_options = ImageTransformer::parseResizeOptions($thumbnail_size);
    } else {
        $resize_options = ['h' => 128];
    }

    // order
    switch ($type) {
        case 'random':
            $order = 'RAND()';
            break;
        case 'order':
            $order = 'ord ASC';
            break;
        case 'new':
        default:
            $order = 'id DESC';
    }

    // list images
    $result = '';
    $rimgs = DB::query('SELECT id,title,prev,full FROM ' . DB::table('gallery_image') . ' WHERE ' . $home_cond . ' ORDER BY ' . $order . ' LIMIT ' . $limit);

    while ($rimg = DB::row($rimgs)) {
        $result .= Gallery::renderImage($rimg, 'hcm' . Hcm::$uid, $resize_options);
    }

    return $result;
};
