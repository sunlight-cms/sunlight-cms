<?php

use Sunlight\Core;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Response;

require '../../system/bootstrap.php';
Core::init('../../', [
    'env' => Core::ENV_ADMIN,
]);

/* ---  vystup  --- */

if (!User::isSuperAdmin()) {
    Response::redirect(Router::adminIndex(['absolute' => true]));
    exit;
}

phpinfo();
