<?php

use Sunlight\Email;

defined('_root') or exit;

return function ($email = "") {
    return Email::generate($email);
};
