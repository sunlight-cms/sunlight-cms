<?php

use Sunlight\User;

defined('SL_ROOT') or exit;

$_admin->title = _lang('login.title');
$_admin->loginLayout = true;

// output
$output .= User::renderLoginForm(false, User::isLoggedIn());
