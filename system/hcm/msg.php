<?php

use Sunlight\Message;

return function ($type, $text, $isHtml = true) {
    return (string) new Message($type, $text, $isHtml);
};
