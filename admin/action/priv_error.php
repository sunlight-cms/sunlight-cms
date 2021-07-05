<?php

use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Message;

defined('SL_ROOT') or exit;

/* --- vystup --- */

$_admin->title = _lang('global.error');

$output = '';

$message = _lang(
    'admin.priv_error.' . ($privException instanceof ContentPrivilegeException ? 'content_message' : 'message'),
    ['%message%' => _e($privException->getMessage())]
);

$output .= Message::error($message);
