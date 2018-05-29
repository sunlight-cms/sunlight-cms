<?php

defined('_root') or exit;

return function ($email = "")
{
    return \Sunlight\Email::link($email);
};
