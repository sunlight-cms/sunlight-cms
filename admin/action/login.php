<?php

use Sunlight\Message;
use Sunlight\User;

defined('_root') or exit;

/* --- vystup --- */

$admin_title = _lang('login.title');
$admin_login_layout = true;

if (empty($_POST) || _logged_in) {
    $admin_output .= User::renderLoginForm(false, _logged_in);
} else {
    $admin_output .= "<h1>" . _lang('admin.post_repeat.title') . "</h1>\n";
    $admin_output .= User::renderPostRepeatForm(true, Message::ok(_lang('admin.post_repeat.msg')));
}
