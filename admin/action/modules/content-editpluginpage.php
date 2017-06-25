<?php

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

// inicializace editscriptu
$type = _page_plugin;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if (!$continue) {
    $output .= _msg(_msg_err, _lang('global.badinput'));
    return;
}

// nacist typy pluginu
$ppages = Sunlight\Page\PageManager::getPluginTypes();

// overit dostupnost pluginu
if (!isset($ppages[$type_idt])) {
    $output .= _msg(_msg_err, sprintf(_lang('plugin.error'), $type_idt));

    return;
}
$ppage = $ppages[$type_idt];

// promenne editscriptu
$custom_settings = '';
$custom_save_array = array();

// udalost pripravy editace
$script = null;
Sunlight\Extend::call('ppage.' . $type_idt . '.edit', Sunlight\Extend::args($output, array(
    'page' => $query,
    'new' => $new,
)));

// vlozeni skriptu
$custom_save_array['type_idt'] = array('type' => 'raw', 'nullable' => false);
require _root . 'admin/action/modules/include/page-editscript.php';
