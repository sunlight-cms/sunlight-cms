<?php

defined('_root') or exit;

$_index['title'] = _lang('xsrf.title');
$_index['output'] = '';

$_index['output'] .= \Sunlight\Message::render(_msg_err, _lang('xsrf.msg'));
$_index['output'] .= \Sunlight\User::renderPostRepeatForm();
