<?php

use Sunlight\Message;

if (!defined('_root')) {
    exit;
}

/* --- vystup --- */

$admin_title = _lang('login.title');
$admin_login_layout = true;

if (empty($_POST) || _logged_in) {
    $admin_output .= _userLoginForm(false, _logged_in);
} else {
    $admin_output .= "<h1>" . _lang('admin.post_repeat.title') . "</h1>\n";
    $admin_output .= _postRepeatForm(true, Message::ok(_lang('admin.post_repeat.msg')));
}
