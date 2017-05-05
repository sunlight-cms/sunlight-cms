<?php

require '../bootstrap.php';
Sunlight\Core::init('../../');

if (_xsrfCheck(true)) {
    _userLogout();
}
_returnHeader();
