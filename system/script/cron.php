<?php

use Sunlight\Core;
use Sunlight\Cron;
use Sunlight\Extend;
use Sunlight\Settings;
use Sunlight\Util\Environment;
use Sunlight\Util\Request;

require '../bootstrap.php';
Core::init('../../', [
    'content_type' => 'text/plain; charset=UTF-8',
]);

// check authorization (unless in CLI env)
if (!Environment::isCli()) {
    $auth_key = Settings::get('cron_auth');

    if ($auth_key === '' || Request::get('key') !== $auth_key) {
        http_response_code(401);
        echo 'Unauthorized';
        exit(1);
    }
}

// run cron tasks
$start = microtime(true);
$names = [];
Extend::reg('cron.task', function ($args) use (&$names) {
    $names[] = $args['name'];
});

Cron::run();

// output results
echo date('Y-m-d H:i:s'), ' [', round((microtime(true) - $start) * 1000), 'ms] ', implode(', ', $names), "\n";
