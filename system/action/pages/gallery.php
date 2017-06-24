<?php

if (!defined('_root')) {
    exit;
}

// vychozi nastaveni
if ($_page['var1'] === null) {
    $_page['var1'] = _galdefault_per_row;
}
if ($_page['var2'] === null) {
    $_page['var2'] = _galdefault_per_page;
}
if ($_page['var3'] === null) {
    $_page['var3'] = _galdefault_thumb_h;
}
if ($_page['var4'] === null) {
    $_page['var4'] = _galdefault_thumb_w;
}

// titulek
$_index['title'] = $_page['title'];

// obsah
Sunlight\Extend::call('page.gallery.content.before', $extend_args);
if ($_page['content'] != "") $output .= _parseHCM($_page['content']) . "\n\n<div class='hr gallery-hr'><hr></div>\n\n";
Sunlight\Extend::call('page.gallery.content.after', $extend_args);

// obrazky
$paging = _resultPaging($_index['url'], $_page['var2'], _images_table, "home=" . $id);
$images = DB::query("SELECT * FROM " . _images_table . " WHERE home=" . $id . " ORDER BY ord " . $paging['sql_limit']);
$images_number = DB::size($images);

if ($images_number != 0) {

    $usetable = $_page['var1'] != -1;
    if (_showPagingAtTop()) {
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
        $output .= _galleryImage($img, $id, $_page['var4'], $_page['var3']);
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
    if (_showPagingAtBottom()) {
        $output .= $paging['paging'];
    }

} else {
    $output .= $_lang['misc.gallery.noimages'];
}
