<?php

use Sunlight\Core;

require '../bootstrap.php';
Core::init('../../');

if (_xsrfCheck(true)) {
    _userLogout();
}
_returnHeader();
