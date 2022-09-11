<?php

use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Page\Page;

defined('SL_ROOT') or exit;

// init editscript
$type = Page::PLUGIN;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';

if (!$continue) {
    $output .= Message::error(_lang('global.badinput'));
    return;
}

// load plugin types
$plugin_types = Page::getPluginTypes();

// check plugin availability
if (!isset($plugin_types[$type_idt])) {
    $output .= Message::error(_lang('plugin.error', ['%plugin%' => $type_idt]), true);

    return;
}

$plugin_type = $plugin_types[$type_idt];

// editscript vars
$custom_settings = '';
$custom_save_array = [];

$script = null;
Extend::call('admin.page.plugin.' . $type_idt . '.edit', Extend::args($output, [
    'page' => $query,
    'new' => $new,
    'custom_settings' => &$custom_settings,
    'custom_save_array' => &$custom_save_array,
]));

// run editscript
$custom_save_array['type_idt'] = ['type' => 'raw', 'nullable' => false];
require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
