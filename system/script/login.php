<?php

use Sunlight\Core;

require '../bootstrap.php';
Core::init('../../', array(
    'session_regenerate' => true,
));

// priprava
$username = \Sunlight\Util\Request::post('login_username');
$password = \Sunlight\Util\Request::post('login_password');
$persistent = \Sunlight\Util\Form::loadCheckbox('login_persistent');

// proces prihlaseni
$result = \Sunlight\User::submitLogin($username, $password, $persistent);

// presmerovani
if ($result !== 1 && isset($_POST['login_form_url'])) {
    $_SESSION['login_form_username'] = $username;

    \Sunlight\Response::redirectBack(\Sunlight\Util\UrlHelper::appendParams(
        \Sunlight\Util\Request::post('login_form_url'),
        'login_form_result=' . $result,
        false
    ));
} else {
    \Sunlight\Response::redirectBack();
}
