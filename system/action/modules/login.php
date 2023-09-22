<?php

use Sunlight\Extend;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;

defined('SL_ROOT') or exit;

// output
$_index->title = _lang('login.title');

$output .= User::renderLoginForm(true);

// show login links if logged in
if (User::isLoggedIn()) {
    $output .= '<h2>' . _lang('login.links') . "</h2>\n";
    $output .= "<div class=\"user-login-actions\">\n<ul>\n";

    $items = [
        [Router::module('profile', ['query' => ['id' => User::getUsername()]]), _lang('mod.profile'), true],
        [Router::module('settings'), _lang('mod.settings'), true],
        [Router::module('messages'), _lang('mod.messages') . ' [' . _num(User::getUnreadPmCount()) . ']', Settings::get('messages')],
        [Router::adminIndex(), _lang('global.admintitle'), User::hasPrivilege('administration')],
    ];

    Extend::call('mod.login.links', ['items' => &$items]);

    foreach ($items as $item) {
        if ($item[2]) {
            $output .= '<li><a href="' . _e($item[0]) . '">' . $item[1] . "</a></li>\n";
        }
    }

    $output .= "</ul>\n</div>\n";
}
