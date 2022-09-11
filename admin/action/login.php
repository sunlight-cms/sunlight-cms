<?php

use Sunlight\Message;
use Sunlight\User;

defined('SL_ROOT') or exit;

$_admin->title = _lang('login.title');
$_admin->loginLayout = true;

// output
if (empty($_POST) || User::isLoggedIn()) {
    $output .= User::renderLoginForm(false, User::isLoggedIn());
} else {
    $output .= '<h1>' . _lang('admin.post_repeat.title') . "</h1>\n";
    $output .= User::renderPostRepeatForm(true, Message::ok(_lang('admin.post_repeat.msg')));
}
