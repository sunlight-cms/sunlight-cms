<?php

use Sunlight\Admin\Admin;
use Sunlight\Message;
use Sunlight\Router;

defined('SL_ROOT') or exit;

/* --- priprava --- */

$other_modules = [
    'system' => [],
    'plugin' => [],
];
foreach ($_admin->modules as $module => $module_options) {
    if (isset($module_options['other']) && $module_options['other'] && Admin::moduleAccess($module)) {
        $type = isset($module_options['other_system']) && $module_options['other_system'] ? 'system' : 'plugin';
        $other_modules[$type][$module] = ($module_options['other_order'] ?? 0);
    }
}
asort($other_modules['system'], SORT_NUMERIC);
asort($other_modules['plugin'], SORT_NUMERIC);

/* ---  vystup  --- */

$output .= '<p>' . _lang('admin.other.p') . '</p>';

if (empty($other_modules['system']) && empty($other_modules['plugin'])) {
    $output .= Message::ok(_lang('global.nokit'));
    return;
}

$output .= '
<table class="list list-noborder">
<tr class="valign-top">
';

// vypis
foreach ($other_modules as $type => $modules) {
    if (!empty($modules)) {
        $output .= "<td>\n";
        foreach ($modules as $module => $order) {
            $url = $_admin->modules[$module]['url'] ?? Router::admin($module);
            $icon = $_admin->modules[$module]['other_icon'] ?? Router::path('images/icons/big-cog.png');
            $new_window = isset($_admin->modules[$module]['other_new_window']) && $_admin->modules[$module]['other_new_window'];

            $output .= '<a class="button block" href="' . $url . '"'
                . ($new_window ? ' target="_blank"' : '')
                . '>'
                . '<img class="icon" alt="' . $module . '" src="' . _e($icon) . '">'
                . $_admin->modules[$module]['title']
                . "</a>\n";
        }
        $output .= "</td>\n";
    }
}
$output .= "\n</tr>\n</table>";
