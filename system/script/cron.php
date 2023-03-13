<?php

use Sunlight\Core;
use Sunlight\Cron;
use Sunlight\Extend;
use Sunlight\Settings;
use Sunlight\Util\Request;

require '../bootstrap.php';
Core::init('../../', [
    'content_type' => 'text/plain; charset=UTF-8',
]);

// check authorization
$auth = explode(':', Settings::get('cron_auth'), 2);

if (
    count($auth) !== 2
    || Request::get('user') !== $auth[0]
    || Request::get('password') !== $auth[1]
) {
    http_response_code(401);
    echo 'Unauthorized';
    exit(1);
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
