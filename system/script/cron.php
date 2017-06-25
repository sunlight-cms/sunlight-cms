<?php

use Sunlight\Core;
use Sunlight\Extend;

require '../bootstrap.php';
Core::init('../../', array(
    'run_cron' => false,
    'content_type' => 'text/plain; charset=UTF-8',
));

/* --- autorizace --- */

$auth = explode(':', Core::loadSetting('cron_auth'), 2);
if (
    sizeof($auth) !== 2
    || _get('user') !== $auth[0]
    || _get('password') !== $auth[1]
) {
    header('HTTP/1.0 401 Unauthorized');
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
echo 'OK(', round((microtime(true) - $start) * 1000), 'ms) ', implode(', ', $names);
