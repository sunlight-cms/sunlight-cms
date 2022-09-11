<?php

use Sunlight\Database\Database as DB;
use Sunlight\Gallery;
use Sunlight\Hcm;

return function ($gallery = null, $type = 'new', $thumbnail_size = null, $limit = null) {
    Hcm::normalizeArgument($gallery, 'string');

    if ($gallery !== null && !empty($gallery = explode('-', $gallery))) {
        $home_cond = 'home IN(' . DB::arr($gallery) . ')';
    } else {
        $home_cond = '1';
    }

    if ($limit !== null) {
        $limit = abs((int) $limit);
    } else {
        $limit = 1;
    }

    // size
    if ($thumbnail_size !== null) {
        $thumbnail_size = explode('/', $thumbnail_size, 2);
        if (count($thumbnail_size) === 2) {
            // width and height
            $x = (int) $thumbnail_size[0];
            $y = (int) $thumbnail_size[1];
        } else {
            // height only
            $x = null;
            $y = (int) $thumbnail_size[0];
        }
    } else {
        // default size
        $x = null;
        $y = 128;
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
        $result .= Gallery::renderImage($rimg, 'hcm' . Hcm::$uid, $x, $y);
    }

    return $result;
};
