<?php

use Sunlight\Core;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

require __DIR__ . '/../bootstrap.php';
Core::init('../../');

// load variables
$login = (bool) Request::get('login');
$allow_login = $login && !User::isLoggedIn();
$login_message = null;
$target = Request::get('target');
$do_repeat = true;
$valid = true;

// check request
if (Request::method() !== 'POST' || empty($target)) {
    $valid = false;
}

// login
if ($valid && $login && !User::isLoggedIn()) {
    $username = Request::post('login_username');
    $password = Request::post('login_password');
    $persistent = Form::loadCheckbox('login_persistent');

    $login_result = User::submitLogin($username, $password, $persistent);
    $login_message = User::getLoginMessage($login_result);

    if ($login_result === 1) {
        $allow_login = false;
    } else {
        $do_repeat = false;
    }
}

// output
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
    <title><?= _lang('post_repeat.title') ?></title>
</head>

<body>

    <div id="wrapper">

        <h1><?= _lang($valid ? 'post_repeat.title' : 'global.badinput') ?></h1>

        <?php if ($valid): ?>
            <p>
                <strong><?= _lang('global.action') ?>:</strong>
                <code><?= _e($target) ?></code>
            </p>

            <p id="warning">
                <?= _lang('xsrf.warning', ['%domain%' => Core::getBaseUrl()->getFullHost()]) ?>
            </p>

            <?= User::renderPostRepeatForm(
                $allow_login,
                $login_message,
                $target,
                $do_repeat
            ) ?>
        <?php endif ?>

    </div>

</body>
</html>
