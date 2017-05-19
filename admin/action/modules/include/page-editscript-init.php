<?php

if (!defined('_root')) {
    exit;
}

/* ----  nacteni promennych  ---- */

$continue = false;
$custom_save_array = array();
$custom_settings = "";
$editscript_enable_content = true;
$editscript_enable_heading = true;
$editscript_enable_perex = true;
$editscript_enable_meta = true;
$editscript_enable_show_heading = true;
$editscript_enable_slug = true;
$editscript_enable_layout = true;
$editscript_enable_events = true;
$editscript_enable_visible = true;
$editscript_enable_access = true;
$editscript_extra_row = '';
$editscript_extra_row2 = '';
$editscript_extra = '';
$type_array = Sunlight\Page\PageManager::getTypes();
$plugin_type_array = Sunlight\Page\PageManager::getPluginTypes();

if (isset($_GET['id'])) {
    $id = (int) _get('id');
    $query = DB::queryRow("SELECT * FROM " . _root_table . " WHERE id=" . $id . " AND type=" . $type);
    if ($query !== false) {
        $continue = true;
        $new = false;
        if (_page_plugin == $type) {
            $type_idt = $query['type_idt'];
        } else {
            $type_idt = null;
        }
    }
} else {
    $id = null;
    $new = true;
    $continue = true;

    // zjistit typ plugin stranky
    if (_page_plugin == $type) {
        if (!isset($_GET['idt'])) {
            $continue = false;
            return;
        } else {
            $type_idt = (string) _get('idt');
        }
    } else {
        $type_idt = null;
    }

    // zkontrolovat opravneni pro tvorbu stranek
    if (!_priv_adminroot) {
        $continue = false;
        return;
    }

    /* ---  vychozi data pro novou polozku --- */
    $default_parent = Sunlight\Admin\PageLister::getConfig('current_page');

    if (_page_plugin == $type) {
        $default_title = $plugin_type_array[$type_idt];
    } else {
        $default_title = $_lang['page.type.' . $type_array[$type]];
    }

    $query = array(
        'id' => -1,
        'title' => $default_title,
        'type' => $type,
        'type_idt' => null,
        'node_parent' => $default_parent,
        'ord' => null,
    );

    $query += Sunlight\Page\PageManipulator::getInitialData($type, $type_idt);
}

if ($continue) {
    Sunlight\Extend::call('admin.root.editscript');
}
