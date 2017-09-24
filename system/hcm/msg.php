<?php

use Sunlight\Message;

if (!defined('_root')) {
    exit;
}

function _HCM_msg($type, $text, $isHtml = true)
{
    return (string) new Message($type, $text, $isHtml);
}
