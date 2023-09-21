<?php

use Sunlight\Core;
use Sunlight\Message;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;

require __DIR__ . '/../bootstrap.php';
Core::init();

// load variables
$login = (bool) Request::get('login');
$allow_login = $login && !User::isLoggedIn();
$login_message = null;
$target = Response::getReturnUrl();
$do_repeat = true;
$valid = true;

// check request
if (Request::method() !== 'POST') {
    $valid = false;
}

// login
if ($valid && $login && !User::isLoggedIn()) {
    $username = Request::post('login_username', '');
    $password = Request::post('login_password', '');
    $persistent = Form::loadCheckbox('login_persistent');

    $login_result = User::submitLogin($username, $password, $persistent);
    $login_message = User::getLoginMessage($login_result);

    if ($login_result === User::LOGIN_SUCCESS) {
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
        body {font-family: sans-serif; font-size: 15px; line-height: 120%; background-color: #ccc;}
        input[type=submit] {padding: 10px; cursor: pointer;}
        #wrapper {max-width: 1000px; margin: 20px auto; padding: 0 20px; border: 1px solid #ddd; background-color: #fff;}
        .message {border: 1px solid #e1e1e1; font-weight: bold; margin: 10px 0; padding: 10px;}
        .message-ok {color: #060; background-color: #dfffdf;}
        .message-err, .message-warn {color: #a00; background-color: #ffdfdf;}
        .message > * {font-weight: normal;}
        th, td {padding: 3px 5px; font-weight: normal; text-align: left}
    </style>
    <title><?= _lang('post_repeat.title') ?></title>
</head>

<body>

    <div id="wrapper">

        <h1><?= _lang($valid ? 'post_repeat.title' : 'global.badinput') ?></h1>

        <?php if ($valid): ?>
            <p>
                <strong><?= _lang('global.action') ?>:</strong>
                <?= _e($target) ?>
            </p>

            <?= Message::warning(_lang('xsrf.warning', ['%domain%' => Core::getBaseUrl()->getFullHost()])) ?>

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
