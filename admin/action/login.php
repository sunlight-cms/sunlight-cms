<?php

use Sunlight\Message;
use Sunlight\User;

defined('SL_ROOT') or exit;

/* --- vystup --- */

$admin_title = _lang('login.title');
$admin_login_layout = true;

if (empty($_POST) || User::isLoggedIn()) {
    $admin_output .= User::renderLoginForm(false, User::isLoggedIn());
} else {
    $admin_output .= "<h1>" . _lang('admin.post_repeat.title') . "</h1>\n";
    $admin_output .= User::renderPostRepeatForm(true, Message::ok(_lang('admin.post_repeat.msg')));
}
