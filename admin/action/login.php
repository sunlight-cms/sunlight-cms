<?php

use Sunlight\Message;

if (!defined('_root')) {
    exit;
}

/* --- vystup --- */

$admin_title = $_lang['login.title'];
$admin_login_layout = true;

if (empty($_POST) || _login) {
    $admin_output .= _userLoginForm(false, _login);
} else {
    $admin_output .= "<h1>" . $_lang['admin.post_repeat.title'] . "</h1>\n";
    $admin_output .= _postRepeatForm(true, Message::ok($_lang['admin.post_repeat.msg']));
}
