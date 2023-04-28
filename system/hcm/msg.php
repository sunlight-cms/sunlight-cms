<?php

use Sunlight\Hcm;
use Sunlight\Message;

return function ($type = '', $text = '', $isHtml = true) {
    Hcm::normalizeArgument($type, 'string');
    Hcm::normalizeArgument($text, 'string');
    Hcm::normalizeArgument($isHtml, 'bool');

    return (new Message($type, $text, $isHtml))->__toString();
};
