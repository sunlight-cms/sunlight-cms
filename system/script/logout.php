<?php

use Sunlight\Core;

require '../bootstrap.php';
Core::init('../../');

if (\Sunlight\Xsrf::check(true)) {
    \Sunlight\User::logout();
}
\Sunlight\Response::redirectBack();
