<?php

use Sunlight\Database\Database as DB;
use Sunlight\Gallery;
use Sunlight\Hcm;

return function ($galerie = null, $typ = 'new', $rozmery = null, $limit = null) {
    // nacteni parametru
    Hcm::normalizeArgument($galerie, 'string');

    if ($galerie !== null && !empty($galerie = explode('-', $galerie))) {
        $home_cond = 'home IN(' . DB::arr($galerie) . ')';
    } else {
        $home_cond = '1';
    }

    if ($limit !== null) {
        $limit = abs((int) $limit);
    } else {
        $limit = 1;
    }

    // rozmery
    if ($rozmery !== null) {
        $rozmery = explode('/', $rozmery, 2);
        if (count($rozmery) === 2) {
            // sirka i vyska
            $x = (int) $rozmery[0];
            $y = (int) $rozmery[1];
        } else {
            // pouze vyska
            $x = null;
            $y = (int) $rozmery[0];
        }
    } else {
        // neuvedeno
        $x = null;
        $y = 128;
    }

    // urceni razeni
    switch ($typ) {
        case 'random':
            $razeni = 'RAND()';
            break;
        case 'order':
            $razeni = 'ord ASC';
            break;
        case 'new':
        default:
            $razeni = 'id DESC';
    }

    // vypis obrazku
    $result = '';
    $rimgs = DB::query('SELECT id,title,prev,full FROM ' . DB::table('gallery_image') . ' WHERE ' . $home_cond . ' ORDER BY ' . $razeni . ' LIMIT ' . $limit);
    while ($rimg = DB::row($rimgs)) {
        $result .= Gallery::renderImage($rimg, 'hcm' . Hcm::$uid, $x, $y);
    }

    return $result;
};
