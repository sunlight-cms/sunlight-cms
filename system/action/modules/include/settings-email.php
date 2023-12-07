<?php

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

if (isset($_POST['save'])) {
    $currentPassword = Request::post('current_password', '');
    $newEmail = trim(Request::post('new_email', ''));
    $needsConfirmation = (bool) Settings::get('registration_confirm');
    $errors = [];

    // check IP log
    if ($needsConfirmation && !IpLog::check(IpLog::ANTI_SPAM)) {
        $errors[] = _lang('error.antispam', ['%antispamtimeout%' => Settings::get('antispamtimeout')]);
    }

    // check current password
    if (!User::checkPassword($currentPassword)) {
        $errors[] = _lang('mod.settings.password.error.bad_current');
    }

    // validate e-mail
    if (!Email::validate($newEmail)) {
        $errors[] = _lang('user.msg.bademail');
    } elseif (!User::isEmailAvailable($newEmail)) {
        $errors[] = _lang('user.msg.emailexists');
    }

    // process
    if (empty($errors)) {
        if ($needsConfirmation) {
            // send confirmation email
            $domain = Core::getBaseUrl()->getFullHost();
            $timestamp = time();
            $hash = User::getAuthHash(User::AUTH_EMAIL_CHANGE, User::$data['email'], User::$data['password'], "{$newEmail}\${$timestamp}");
            $link = Router::module('change-email', [
                'query' => [
                    'user' => User::getId(),
                    'new_email' => base64_encode($newEmail),
                    'timestamp' => $timestamp,
                    'hash' => $hash,
                ],
                'absolute' => true,
            ]);

            if (Email::send(
                User::$data['email'],
                _lang('mod.settings.email.confirm.subject', ['%domain%' => $domain]),
                _lang('mod.settings.email.confirm.text', [
                    '%username%' => User::getUsername(),
                    '%new_email%' => $newEmail,
                    '%domain%' => $domain,
                    '%confirm_link%' => $link,
                    '%ip%' => Core::getClientIp(),
                    '%date%' => GenericTemplates::renderTime(time(), 'email'),
                ])
            )) {
                IpLog::update(IpLog::ANTI_SPAM);
                $output .= Message::ok(_lang('mod.settings.email.confirm.sent', ['%email%' => _e($newEmail)]), true);

                return;
            } else {
                $output .= Message::error(_lang('global.emailerror'));
            }
        } else {
            // change without confirmation
            $changeset = ['email' => $newEmail];
            DB::update('user', 'id=' . User::getId(), $changeset);
            Logger::notice('user', sprintf('User "%s" has changed their e-mail', User::getUsername()), ['user_id' => User::getId(), 'new_email' => $newEmail, 'old_email' => User::$data['email']]);
            User::refreshLogin($changeset);
            $output .= Message::ok(_lang('global.saved'));

            return;
        }
    } else {
        $output .= Message::list($errors);
    }
}

$output .= Form::render(
    [
        'name' => 'user_settings_email',
        'table_attrs' => ' class="profiletable"',
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.email') . '</legend>',
        'form_append' => '</fieldset>'
            . Form::input('submit', 'save', _lang('mod.settings.email.submit')),
    ],
    [
        [
            'label' => _lang('mod.settings.email.new'),
            'content' => Form::input('email', 'new_email', Request::post('new_email', User::$data['email']), ['class' => 'inputsmall', 'maxlength' => 191]),
        ],
        [
            'label' => _lang('mod.settings.password.current'),
            'content' => Form::input('password', 'current_password', null, ['class' => 'inputsmall', 'autocomplete' => 'off']),
        ],
    ]
);
