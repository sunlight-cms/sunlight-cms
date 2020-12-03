<?php

use Sunlight\Router;
use Sunlight\User;

defined('_root') or exit;

/* ---  vystup  --- */

$_index['title'] = _lang('login.title');

$output .= User::renderLoginForm(true);

// moznosti
if (_logged_in) {
    $output .= "<h2>" . _lang('login.links') . "</h2>\n<ul>\n";

    // pole polozek (adresa, titulek, podminky pro zobrazeni)
    $items = [
        [Router::generate('admin/'), _lang('global.admintitle'), _priv_administration],
        [Router::module('profile', 'id=' . _user_name), _lang('mod.profile'), true],
        [Router::module('settings'), _lang('mod.settings'), true],
        [Router::module('messages'), _lang('mod.messages') . " [" . User::getUnreadPmCount() . "]", _messages],
    ];

    // vypis
    foreach ($items as $item) {
        if ($item[2]) {
            $output .= "<li><a href='" . _e($item[0]) . "'>" . $item[1] . "</a></li>\n";
        }
    }

    $output .= "</ul>\n";
}
