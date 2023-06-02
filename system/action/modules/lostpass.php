<?php

use Sunlight\Captcha;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

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

// handle form
if (isset($_POST['email'])) {
    $errors = [];

    do {
        $email = Request::post('email', '');

        // validate email
        if (!Email::validate($email)) {
            $errors[] = _lang('user.msg.bademail');
            break;
        }

        // check captcha
        if (!Captcha::check()) {
            $errors[] = _lang('captcha.failure');
            break;
        }

        // check IP log
        if (!IpLog::check(IpLog::PASSWORD_RESET_REQUESTED)) {
            $errors[] = _lang('mod.lostpass.limit', ['%limit%' => Settings::get('lostpassexpire') / 60]);
            break;
        }

        // find user
        $user = DB::queryRow('SELECT id,username,password,email FROM ' . DB::table('user') . ' WHERE email=' . DB::val($email));

        if ($user === false) {
            $errors[] = _lang('mod.lostpass.error.email_not_found');
            break;
        }

        // send email
        $domain = Core::getBaseUrl()->getFullHost();
        $timestamp = time();
        $link = Router::module('lostpass-reset', [
            'query' => [
                'user' => $user['id'],
                'timestamp' => $timestamp,
                'hash' => User::getAuthHash(User::AUTH_PASSWORD_RESET, $user['email'], $user['password'], "{$timestamp}"),
            ],
            'absolute' => true,
        ]);

        if (!Email::send(
            $user['email'],
            _lang('mod.lostpass.email.subject', ['%domain%' => $domain]),
            _lang('mod.lostpass.email.text', [
                '%domain%' => $domain,
                '%username%' => $user['username'],
                '%link%' => $link,
                '%date%' => GenericTemplates::renderTime(time()),
                '%ip%' => Core::getClientIp(),
            ])
        )) {
            $errors[] = _lang('global.emailerror');
            break;
        }

        // all ok
        IpLog::update(IpLog::PASSWORD_RESET_REQUESTED);
        Logger::notice('user', sprintf('Password reset requested for user "%s"', $user['username']), ['user_id' => $user['id']]);
        $output .= Message::ok(_lang('mod.lostpass.email_sent'));

        return;
    } while(false);

    if (!empty($errors)) {
        $output .= Message::list($errors);
    }
}

// form
$output .= '<p class="bborder">' . _lang('mod.lostpass.p') . '</p>';

$output .= Form::render(
    [
        'name' => 'lostpassform',
    ],
    [
        [
            'label' => _lang('global.email'),
            'content' => '<input type="email" class="inputsmall" maxlength="191"' . Form::restorePostValueAndName('email', '@') . ' autocomplete="email">',
        ],
        Captcha::init(),
        Form::getSubmitRow(['text' => _lang('global.send')]),
    ]
);
