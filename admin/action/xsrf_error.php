<?php

defined('_root') or exit;

/* --- vystup --- */

$admin_title = _lang('xsrf.title');

$admin_output .= "<h1>" . _lang('xsrf.title') . "</h1>\n";
$admin_output .= \Sunlight\Message::render(_msg_err, _lang('xsrf.msg'));
$admin_output .= \Sunlight\User::renderPostRepeatForm();
