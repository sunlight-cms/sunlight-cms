<?php

use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Page\Page;

defined('SL_ROOT') or exit;

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

// inicializace editscriptu
$type = Page::PLUGIN;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';
if (!$continue) {
    $output .= Message::error(_lang('global.badinput'));
    return;
}

// nacist typy pluginu
$plugin_types = Page::getPluginTypes();

// overit dostupnost pluginu
if (!isset($plugin_types[$type_idt])) {
    $output .= Message::error(_lang('plugin.error', ['%plugin%' => $type_idt]), true);

    return;
}
$plugin_type = $plugin_types[$type_idt];

// promenne editscriptu
$custom_settings = '';
$custom_save_array = [];

// udalost pripravy editace
$script = null;
Extend::call('admin.page.plugin.' . $type_idt . '.edit', Extend::args($output, [
    'page' => $query,
    'new' => $new,
    'custom_settings' => &$custom_settings,
    'custom_save_array' => &$custom_save_array,
]));

// vlozeni skriptu
$custom_save_array['type_idt'] = ['type' => 'raw', 'nullable' => false];
require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
