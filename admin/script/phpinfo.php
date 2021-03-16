<?php

use Sunlight\Core;
use Sunlight\Router;
use Sunlight\Util\Response;

require '../../system/bootstrap.php';
Core::init('../../', [
    'env' => Core::ENV_ADMIN,
]);

/* ---  vystup  --- */

if (!_priv_super_admin) {
    Response::redirect(Router::generate('admin/'));
    exit;
}

phpinfo();
