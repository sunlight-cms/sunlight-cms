<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;

defined('_root') or exit;

return function ($galerie = "", $typ = 'new', $rozmery = null, $limit = null)
{
    // nacteni parametru
    $result = "";
    $galerie = _sqlWhereColumn("home", $galerie);
    if (isset($limit)) {
        $limit = abs((int) $limit);
    } else {
        $limit = 1;
    }

    // rozmery
    if (isset($rozmery)) {
        $rozmery = explode('/', $rozmery, 2);
        if (sizeof($rozmery) === 2) {
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
        case 2:
            $razeni = "RAND()";
            break;
        case 'order':
        case 3:
            $razeni = "ord ASC";
            break;
        case 'new':
        default:
            $razeni = "id DESC";
    }

    // vypis obrazku
    $rimgs = DB::query("SELECT id,title,prev,full FROM " . _images_table . " WHERE " . $galerie . " ORDER BY " . $razeni . " LIMIT " . $limit);
    while($rimg = DB::row($rimgs)) {
        $result .= _galleryImage($rimg, "hcm" . Core::$hcmUid, $x, $y);
    }

    return $result;
};
