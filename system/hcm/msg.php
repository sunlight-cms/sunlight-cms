<?php

use Sunlight\Message;

defined('_root') or exit;

return function ($type, $text, $isHtml = true) {
    return (string) new Message($type, $text, $isHtml);
};
