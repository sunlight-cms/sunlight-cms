<?php

if (!defined('_root')) {
    exit;
}

/* --- priprava --- */

$other_modules = array(
    'system' => array(),
    'plugin' => array(),
);
foreach ($admin_modules as $module => $module_options) {
    if (isset($module_options['other']) && $module_options['other'] && _adminModuleAccess($module)) {
        $type = isset($module_options['other_system']) && $module_options['other_system'] ? 'system' : 'plugin';
        $other_modules[$type][$module] = (isset($module_options['other_order']) ? $module_options['other_order'] : 0);
    }
}
asort($other_modules['system'], SORT_NUMERIC);
asort($other_modules['plugin'], SORT_NUMERIC);

/* ---  vystup  --- */

$output .= "<p>" . _lang('admin.other.p') . "</p>";

if (empty($other_modules['system']) && empty($other_modules['plugin'])) {
    $output .= _msg(_msg_ok, _lang('global.nokit'));
    return;
}

$output .= "
<table class='list list-noborder'>
<tr class='valign-top'>
";

// vypis
foreach ($other_modules as $type => $modules) {
    if (!empty($modules)) {
        $output .= "<td>\n";
        foreach ($modules as $module => $order) {
            $url = isset($admin_modules[$module]['url'])
                ? $admin_modules[$module]['url']
                : 'index.php?p=' . $module;
            $icon = isset($admin_modules[$module]['other_icon'])
                ? $admin_modules[$module]['other_icon']
                : 'images/icons/big-cog.png';
            $new_window = isset($admin_modules[$module]['other_new_window']) && $admin_modules[$module]['other_new_window'];

            $output .= '<a class="button block" href="' . $url . '"'
                . ($new_window ? ' target="_blank"' : '')
                . '>'
                . '<img class="icon" alt="' . $module . '" src="' . $icon . '">'
                . $admin_modules[$module]['title']
                . "</a>\n";
        }
        $output .= "</td>\n";
    }
}
$output .= "\n</tr>\n</table>";
