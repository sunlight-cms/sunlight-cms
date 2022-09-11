<?php

use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Message;

defined('SL_ROOT') or exit;

$_admin->title = _lang('global.error');

// output
$output = '';

$message = _lang(
    'admin.priv_error.' . ($privException instanceof ContentPrivilegeException ? 'content_message' : 'message'),
    ['%message%' => _e($privException->getMessage())]
);

$output .= Message::error($message);
