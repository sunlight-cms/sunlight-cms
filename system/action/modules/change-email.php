<?php

use Sunlight\IpLog;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Util\StringHelper;

defined('SL_ROOT') or exit;

if (!Settings::get('registration_confirm')) {
    $_index->notFound();
    return;
}

// output
$_index->title = _lang('mod.settings.email');

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
    $newEmail = base64_decode(Request::get('new_email'));
    $timestamp = Request::get('timestamp', '');
    $hash = Request::get('hash', '');

    if (
        !ctype_digit($userId) 
        || $newEmail === false 
        || !Email::validate($newEmail) 
        || !ctype_digit($timestamp) 
        || !ctype_xdigit($hash)
    ) {
        break;
    }

    // verify timestamp
    if ((int) $timestamp + 3600 < time()) {
        break;
    }

    // verify e-mail availability
    if (!User::isEmailAvailable($newEmail)) {
        $error = StringHelper::ucfirst(_lang('user.msg.emailexists'));
        break;
    }

    // find user
    $user = DB::queryRow('SELECT id,username,password,email FROM ' . DB::table('user') . ' WHERE id=' . DB::val($userId));

    if ($user === false) {
        break;
    }

    // verify hash
    if (!hash_equals(User::getAuthHash(User::AUTH_EMAIL_CHANGE, $user['email'], $user['password'], "{$newEmail}\${$timestamp}"), $hash)) {
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
if (isset($_POST['confirm'])) {
    $changeset = ['email' => $newEmail];
    DB::update('user', 'id=' . DB::val($user['id']), $changeset);
    Logger::notice('user', sprintf('User "%s" has changed their e-mail', $user['username']), ['user_id' => $user['id'], 'new_email' => $newEmail, 'old_email' => $user['email']]);

    if (User::isLoggedIn() && User::equals($user['id'])) {
        User::refreshLogin($changeset);
    }

    $output .= Message::ok(_lang('mod.change-email.success'));

    return;
}

// form
$output .= '<p class="bborder">' . _lang('mod.change-email.p') . '</p>';

$output .= Form::render(
    [
        'name' => 'changeemailform',
    ],
    [
        [
            'label' => _lang('global.user'),
            'content' => Form::input('text', null, $user['username'], ['class' => 'inputsmall', 'disabled' => true]),
        ],
        [
            'label' => _lang('mod.settings.email.new'),
            'content' => Form::input('text', null, $newEmail, ['class' => 'inputsmall', 'disabled' => true]),
        ],
        Form::getSubmitRow(['text' => _lang('mod.settings.email.submit'), 'name' => 'confirm']),
    ]
);
