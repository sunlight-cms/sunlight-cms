<?php

use Sunlight\Message;
use Sunlight\User;

defined('_root') or exit;

$_index['title'] = _lang('xsrf.title');
$_index['output'] = '';

$_index['output'] .= Message::render(_msg_err, _lang('xsrf.msg'));
$_index['output'] .= User::renderPostRepeatForm();
