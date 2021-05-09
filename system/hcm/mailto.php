<?php

use Sunlight\Email;

return function ($email = "") {
    return Email::link($email);
};
