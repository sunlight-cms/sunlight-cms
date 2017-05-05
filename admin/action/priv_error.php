<?php

use Sunlight\Exception\ContentPrivilegeException;

if (!defined('_root')) {
    exit;
}

/* --- vystup --- */

$admin_title = $_lang['global.error'];

$output = '';

$message = sprintf(
    $_lang['admin.priv_error.' . ($privException instanceof ContentPrivilegeException ? 'content_message' : 'message')],
    _e($privException->getMessage())
);

$admin_output .= _msg(_msg_err, $message);
