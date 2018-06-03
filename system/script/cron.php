<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Util\Request;
use Sunlight\Util\Response;

require '../bootstrap.php';
Core::init('../../', array(
    'content_type' => 'text/plain; charset=UTF-8',
));

/* --- autorizace --- */

$auth = explode(':', Core::loadSetting('cron_auth'), 2);
if (
    sizeof($auth) !== 2
    || Request::get('user') !== $auth[0]
    || Request::get('password') !== $auth[1]
) {
    Response::unauthorized();
    echo 'Unauthorized';
    exit(1);
}

/* ---  spusteni cronu  --- */

// priprava
$start = microtime(true);
$names = array();
Extend::reg('cron', function ($args) use (&$names) {
    $names[] = $args['name'];
});

// spusteni
Core::runCronTasks();

// vysledek
echo date('Y-m-d H:i:s'), ' [', round((microtime(true) - $start) * 1000), 'ms] ', implode(', ', $names), "\n";
