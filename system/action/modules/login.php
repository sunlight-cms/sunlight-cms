<?php

use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;

defined('SL_ROOT') or exit;

/* ---  vystup  --- */

$_index->title = _lang('login.title');

$output .= User::renderLoginForm(true);

// moznosti
if (User::isLoggedIn()) {
    $output .= '<h2>' . _lang('login.links') . "</h2>\n<ul>\n";

    // pole polozek (adresa, titulek, podminky pro zobrazeni)
    $items = [
        [Router::adminIndex(), _lang('global.admintitle'), User::hasPrivilege('administration')],
        [Router::module('profile', ['query' => ['id' => User::getUsername()]]), _lang('mod.profile'), true],
        [Router::module('settings'), _lang('mod.settings'), true],
        [Router::module('messages'), _lang('mod.messages') . ' [' . User::getUnreadPmCount() . ']', Settings::get('messages')],
    ];

    // vypis
    foreach ($items as $item) {
        if ($item[2]) {
            $output .= '<li><a href="' . _e($item[0]) . '">' . $item[1] . "</a></li>\n";
        }
    }

    $output .= "</ul>\n";
}
