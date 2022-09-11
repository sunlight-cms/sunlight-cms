<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Settings;
use Sunlight\Util\Request;
use Sunlight\Util\Response;

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
    Response::unauthorized();
    echo 'Unauthorized';
    exit(1);
}

// run cron tasks
$start = microtime(true);
$names = [];
Extend::reg('cron', function ($args) use (&$names) {
    $names[] = $args['name'];
});

Core::runCronTasks();

// output results
echo date('Y-m-d H:i:s'), ' [', round((microtime(true) - $start) * 1000), 'ms] ', implode(', ', $names), "\n";
