<?php

use Sunlight\Core;

require '../bootstrap.php';
Core::init('../../', array(
    'session_regenerate' => true,
));

// priprava
$username = _post('login_username');
$password = _post('login_password');
$persistent = _checkboxLoad('login_persistent');

// proces prihlaseni
$result = _userLoginSubmit($username, $password, $persistent);

// presmerovani
if ($result !== 1 && isset($_POST['login_form_url'])) {
    $_SESSION['login_form_username'] = $username;

    _returnHeader(_addGetToLink(
        _post('login_form_url'),
        'login_form_result=' . $result,
        false
    ));
} else {
    _returnHeader();
}
