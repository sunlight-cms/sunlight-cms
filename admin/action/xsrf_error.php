<?php

use Sunlight\Message;
use Sunlight\User;

defined('SL_ROOT') or exit;

/* --- vystup --- */

$admin_title = _lang('xsrf.title');

$admin_output .= "<h1>" . _lang('xsrf.title') . "</h1>\n";
$admin_output .= Message::error(_lang('xsrf.msg'));
$admin_output .= User::renderPostRepeatForm();
