<?php

use Sunlight\Message;
use Sunlight\User;

defined('SL_ROOT') or exit;

$_admin->title = _lang('xsrf.title');

// output
$output .= '<h1>' . _lang('xsrf.title') . "</h1>\n";
$output .= Message::error(_lang('xsrf.msg'));
$output .= User::renderPostRepeatForm();
