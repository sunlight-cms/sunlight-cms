<?php

defined('_root') or exit;

$_index['title'] = _lang('xsrf.title');
$_index['output'] = '';

$_index['output'] .= _msg(_msg_err, _lang('xsrf.msg'));
$_index['output'] .= _postRepeatForm();
