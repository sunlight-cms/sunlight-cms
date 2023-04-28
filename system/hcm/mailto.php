<?php

use Sunlight\Email;
use Sunlight\Hcm;

return function ($email = '') {
    Hcm::normalizeArgument($email, 'string');

    return Email::link($email);
};
