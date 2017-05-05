<?php

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_separator;
require _root . 'admin/action/modules/include/page-editscript-init.php';

if ($continue) {
    $editscript_enable_content = false;
    $editscript_enable_heading = false;
    $editscript_enable_perex = false;
    $editscript_enable_meta = false;
    $editscript_enable_show_heading = false;
    $editscript_enable_slug = false;
    $editscript_enable_events = false;
    $editscript_enable_layout = false;
    $editscript_enable_visible = false;
    $editscript_enable_access = false;
}

require _root . 'admin/action/modules/include/page-editscript.php';
