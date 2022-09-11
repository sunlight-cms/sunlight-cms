<?php

use Sunlight\Page\Page;

defined('SL_ROOT') or exit;

$type = Page::SEPARATOR;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';

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

require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
