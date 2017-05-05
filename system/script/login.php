<?php

require '../bootstrap.php';
Sunlight\Core::init('../../', array(
    'session_regenerate' => true,
));

// priprava
$username = _post('login_username');
$password = _post('login_password');
$persistent = _checkboxLoad('login_persistent');

// proces prihlaseni
$result = _userLoginSubmit($username, $password, $persistent);

// presmerovani
if (1 !== $result && isset($_POST['login_form_url'])) {
    _returnHeader(_addGetToLink(
        _post('login_form_url'),
        'login_form_result=' . $result . '&login_form_username=' . rawurlencode($username),
        false
    ));
} else {
    _returnHeader();
}
