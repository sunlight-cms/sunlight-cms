<?php

use Sunlight\IpLog;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Database\Database as DB;

defined('SL_ROOT') or exit;

if (!Settings::get('lostpass')) {
    $_index->notFound();
    return;
}

if (User::isLoggedIn()) {
    $_index->redirect(Router::module('login', ['absolute' => true]));
    return;
}

// output
$_index->title = _lang('mod.lostpass');

// load and validate link
$valid = false;
$error = null;

do {
    // check login attempt limit
    if (!IpLog::check(IpLog::FAILED_LOGIN_ATTEMPT)) {
        $error = _lang('login.attemptlimit', ['%minutes%' => _num(Settings::get('maxloginexpire') / 60)]);
        break;
    }

    // load params
    $userId = Request::get('user', '');
    $timestamp = Request::get('timestamp', '');
    $hash = Request::get('hash', '');

    if (!ctype_digit($userId) || !ctype_digit($timestamp) || !ctype_xdigit($hash)) {
        break;
    }

    // verify timestamp
    if ((int) $timestamp + 3600 < time()) {
        break;
    }

    // find user
    $user = DB::queryRow('SELECT id,username,password,email FROM ' . DB::table('user') . ' WHERE id=' . DB::val($userId));

    if ($user === false) {
        break;
    }

    // verify hash
    if (!hash_equals(User::getAuthHash(User::AUTH_PASSWORD_RESET, $user['email'], $user['password'], $timestamp), $hash)) {
        break;
    }

    // all ok
    $valid = true;
} while(false);

if (!$valid) {
    IpLog::update(IpLog::FAILED_LOGIN_ATTEMPT);
    $output .= Message::warning($error ?? _lang('error.bad_link'));
    return;
}

// handle form
if (isset($_POST['new_password'])) {
    $newPassword = Request::post('new_password', '');
    $newPasswordCheck = Request::post('new_password_check', '');
    $errors = [];

    if (!Password::validate($newPassword, $newPasswordCheck, $newPasswordErr)) {
        $errors[] = Password::getErrorMessage($newPasswordErr);
    }

    if (empty($errors)) {
        DB::update('user', 'id=' . $user['id'], ['password' => Password::create($newPassword)->build()]);
        Logger::notice('user', sprintf('User "%s" has reset their password', $user['username']), ['user_id' => $user['id']]);
        $_SESSION['login_form_username'] = $user['username'];
        $output .= Message::ok(_lang('mod.lostpass.reset.success', ['%login_link%' => _e(Router::module('login'))]), true);
        return;
    } else {
        $output .= Message::list($errors);
    }
}

// form
$output .= '<p class="bborder">' . _lang('mod.lostpass.reset.p') . '</p>';

$output .= Form::render(
    [
        'name' => 'lostpassresetform',
    ],
    [
        [
            'label' => _lang('mod.settings.password.new'),
            'content' => Form::input('password', 'new_password', null, ['class' => 'inputsmall', 'autocomplete' => 'new-password']),
        ],
        [
            'label' => _lang('mod.settings.password.new') . ' (' . _lang('global.check') . ')',
            'content' => Form::input('password', 'new_password_check', null, ['class' => 'inputsmall', 'autocomplete' => 'new-password']),
        ],
        Form::getSubmitRow(['text' => _lang('mod.settings.password.submit')]),
    ]
);
