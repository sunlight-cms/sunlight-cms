<?php

use Sunlight\Admin\PageLister;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Page\Page;
use Sunlight\Page\PageManipulator;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;

defined('SL_ROOT') or exit;

$continue = false;
$custom_save_array = [];
$custom_settings = '';
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
$editscript_setting_extra = '';
$editscript_setting_extra2 = '';
$type_array = Page::getTypes();
$plugin_type_array = Page::getPluginTypes();

if (isset($_GET['id'])) {
    $id = (int) Request::get('id');
    $query = DB::queryRow('SELECT * FROM ' . DB::table('page') . ' WHERE id=' . $id . ' AND type=' . $type);

    if ($query !== false) {
        $continue = true;
        $new = false;

        if ($type == Page::PLUGIN) {
            $type_idt = $query['type_idt'];
        } else {
            $type_idt = null;
        }
    }
} else {
    $id = null;
    $new = true;
    $continue = true;

    // get plugin page type
    if ($type == Page::PLUGIN) {
        if (!isset($_GET['idt'])) {
            $continue = false;
            return;
        }

        $type_idt = (string) Request::get('idt');
    } else {
        $type_idt = null;
    }

    // check privilege for page creation
    if (!User::hasPrivilege('adminpages')) {
        $continue = false;
        return;
    }

    // set default data
    $default_parent = PageLister::getConfig('current_page');

    if ($type == Page::PLUGIN) {
        $default_title = $plugin_type_array[$type_idt];
    } else {
        $default_title = _lang('page.type.' . $type_array[$type]);
    }

    $default_title = StringManipulator::ucfirst($default_title);

    $query = [
        'id' => -1,
        'title' => $default_title,
        'type' => $type,
        'type_idt' => null,
        'node_parent' => $default_parent,
        'ord' => null,
    ];

    $query += PageManipulator::getInitialData($type, $type_idt);
}

if ($continue) {
    Extend::call('admin.page.editscript');
}
