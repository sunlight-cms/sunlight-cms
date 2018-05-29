<?php

use Sunlight\Exception\ContentPrivilegeException;

defined('_root') or exit;

/* --- vystup --- */

$admin_title = _lang('global.error');

$output = '';

$message = sprintf(
    _lang('admin.priv_error.' . ($privException instanceof ContentPrivilegeException ? 'content_message' : 'message')),
    _e($privException->getMessage())
);

$admin_output .= \Sunlight\Message::render(_msg_err, $message);
