<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Gallery;
use Sunlight\Hcm;
use Sunlight\Paginator;
use Sunlight\Settings;

defined('_root') or exit;

// vychozi nastaveni
if ($_page['var1'] === null) {
    $_page['var1'] = Settings::get('galdefault_per_row');
}
if ($_page['var2'] === null) {
    $_page['var2'] = Settings::get('galdefault_per_page');
}
if ($_page['var3'] === null) {
    $_page['var3'] = Settings::get('galdefault_thumb_h');
}
if ($_page['var4'] === null) {
    $_page['var4'] = Settings::get('galdefault_thumb_w');
}

// titulek
$_index['title'] = $_page['title'];

// obsah
Extend::call('page.gallery.content.before', $extend_args);
if ($_page['content'] != "") $output .= Hcm::parse($_page['content']) . "\n\n<div class='hr gallery-hr'><hr></div>\n\n";
Extend::call('page.gallery.content.after', $extend_args);

// obrazky
$paging = Paginator::render($_index['url'], $_page['var2'], _gallery_image_table, "home=" . $id);
$images = DB::query("SELECT * FROM " . _gallery_image_table . " WHERE home=" . $id . " ORDER BY ord " . $paging['sql_limit']);
$images_number = DB::size($images);

if ($images_number != 0) {

    $usetable = $_page['var1'] != -1;
    if (Paginator::atTop()) {
        $output .= $paging['paging'];
    }
    if ($usetable) {
        $output .= "<table class='gallery'>\n";
    } else {
        $output .= "<div class='gallery'>\n";
    }

    // obrazky
    $counter = 0;
    $cell_counter = 0;
    while ($img = DB::row($images)) {
        if ($usetable && $cell_counter == 0) {
            $output .= "<tr>\n";
        }

        // bunka
        if ($usetable) {
            $output .= "<td>";
        }
        $output .= Gallery::renderImage($img, $id, $_page['var4'], $_page['var3']);
        if ($usetable) {
            $output .= "</td>";
        }

        $cell_counter++;
        if ($usetable && ($cell_counter == $_page['var1'] || $counter == $images_number - 1)) {
            $cell_counter = 0;
            $output .= "\n</tr>";
        }
        $output .= "\n";
        $counter++;
    }

    if ($usetable) {
        $output .= "</table>";
    } else {
        $output .= "</div>";
    }
    if (Paginator::atBottom()) {
        $output .= $paging['paging'];
    }

} else {
    $output .= _lang('misc.gallery.noimages');
}
