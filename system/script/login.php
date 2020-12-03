<?php

use Sunlight\Core;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Util\UrlHelper;

require '../bootstrap.php';
Core::init('../../', [
    'session_regenerate' => true,
]);

// priprava
$username = Request::post('login_username');
$password = Request::post('login_password');
$persistent = Form::loadCheckbox('login_persistent');

// proces prihlaseni
$result = User::submitLogin($username, $password, $persistent);

// presmerovani
if ($result !== 1 && isset($_POST['login_form_url'])) {
    $_SESSION['login_form_username'] = $username;

    Response::redirectBack(UrlHelper::appendParams(
        Request::post('login_form_url'),
        'login_form_result=' . $result
    ));
} else {
    Response::redirectBack();
}
