<?php

use Sunlight\Admin\Admin;
use Sunlight\Extend;
use Sunlight\Message;

defined('SL_ROOT') or exit;

/* --- vystup --- */

if (isset($_admin->modules[$_admin->currentModule])) {
    if (Admin::moduleAccess($_admin->currentModule)) {

        $module = $_admin->modules[$_admin->currentModule];
        $module_custom_header = (isset($module['custom_header']) && $module['custom_header']);

        // zpetny odkaz
        if (isset($module['parent']) && !$module_custom_header) {
            $output .= Admin::backlink('index.php?p=' . $module['parent']);
        }

        // titulek
        $_admin->title = $module['title'];
        if (!$module_custom_header) {
            $output .= '<h1>' . $module['title'] . "</h1>\n";
        }

        // urceni skriptu
        $script = $module['script'] ?? SL_ROOT . 'admin/action/modules/' . $_admin->currentModule . '.php';

        // vlozeni
        $extend_args = Extend::args($output, [
            'name' => $_admin->currentModule,
            'script' => &$script,
        ]);
        Extend::call('admin.mod.init', $extend_args);
        Extend::call('admin.mod.' . $_admin->currentModule . '.before', $extend_args);

        if ($script !== false && file_exists($script)) {
            require $script;

            $extend_args = Extend::args($output);
            Extend::call('admin.mod.' . $_admin->currentModule . '.after', $extend_args);
            Extend::call('admin.mod.after', $extend_args);
        } else {
            $output .= Message::warning(_lang('admin.moduleunavailable'));
        }
    } else {
        // pristup odepren
        $output .= '<h1>' . _lang('global.error') . "</h1>\n" . Message::warning(_lang('global.accessdenied'));
    }
} else {
    // modul neexistuje
    $output .= '<h1>' . _lang('global.error404.title') . "</h1>\n" . Message::warning(_lang('global.error404'));
}
