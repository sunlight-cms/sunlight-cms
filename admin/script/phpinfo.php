<?php

use Sunlight\Core;

require '../../system/bootstrap.php';
Core::init('../../', array(
    'env' => Core::ENV_ADMIN,
));

/* ---  vystup  --- */

if (!_priv_super_admin) {
    exit;
}

phpinfo();
