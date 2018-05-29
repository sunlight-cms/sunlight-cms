<?php

use Sunlight\Message;

if (!defined('_root')) {
    exit;
};

return function ($type, $text, $isHtml = true)
{
    return (string) new Message($type, $text, $isHtml);
};
