<?php

use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;

defined('SL_ROOT') or exit;

// output
$_index->title = _lang('login.title');

$output .= User::renderLoginForm(true);

// show login links if loggen in
if (User::isLoggedIn()) {
    $output .= '<h2>' . _lang('login.links') . "</h2>\n<ul>\n";

    $items = [
        [Router::adminIndex(), _lang('global.admintitle'), User::hasPrivilege('administration')],
        [Router::module('profile', ['query' => ['id' => User::getUsername()]]), _lang('mod.profile'), true],
        [Router::module('settings'), _lang('mod.settings'), true],
        [Router::module('messages'), _lang('mod.messages') . ' [' . User::getUnreadPmCount() . ']', Settings::get('messages')],
    ];

    foreach ($items as $item) {
        if ($item[2]) {
            $output .= '<li><a href="' . _e($item[0]) . '">' . $item[1] . "</a></li>\n";
        }
    }

    $output .= "</ul>\n";
}
