<?php

use Sunlight\Core;
use Sunlight\Util\Url;

require '../bootstrap.php';
Core::init('../../');

// priprava
$login = (bool) _get('login');
$allow_login = $login && !_logged_in;
$login_message = null;
$target = _get('target');
$do_repeat = true;
$valid = true;

// kontrola
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($target)) {
    $valid = false;
}

// prihlaseni
if ($valid && $login && !_logged_in) {
    $username = _post('login_username');
    $password = _post('login_password');
    $persistent = _checkboxLoad('login_persistent');

    $login_result = _userLoginSubmit($username, $password, $persistent);
    $login_message = _userLoginMessage($login_result);

    if ($login_result === 1) {
        $allow_login = false;
    } else {
        $do_repeat = false;
    }
}

// vystup
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {font-family: sans-serif; background-color: #ccc;}
        input[type=submit] {padding: 10px; cursor: pointer;}
        #wrapper {max-width: 600px; margin: 0 auto; padding: 0 20px; border: 1px solid #ddd; background-color: #fff;}
        #warning {color: #a00;}
    </style>
    <title><?php echo _lang('post_repeat.title') ?></title>
</head>

<body>

    <div id="wrapper">

        <h1><?php echo _lang($valid ? 'post_repeat.title' : 'global.badinput') ?></h1>

        <?php if ($valid): ?>
            <p>
                <strong><?php echo _lang('global.action') ?>:</strong>
                <code><?php echo _e($target) ?></code>
            </p>

            <p id="warning">
                <?php echo _lang('xsrf.warning', array('*domain*' => Url::base()->getFullHost())) ?>
            </p>

            <?php echo _postRepeatForm(
                $allow_login,
                $login_message,
                $target,
                $do_repeat
            ) ?>
        <?php endif ?>

    </div>

</body>
</html>
