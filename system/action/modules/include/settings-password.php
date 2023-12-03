<?php

use Sunlight\Database\Database as DB;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

if (isset($_POST['save'])) {
    $currentPassword = Request::post('current_password', '');
    $newPassword = Request::post('new_password', '');
    $newPasswordCheck = Request::post('new_password_check', '');
    $errors = [];

    if (!User::checkPassword($currentPassword)) {
        $errors[] = _lang('mod.settings.password.error.bad_current');
    }

    if (!Password::validate($newPassword, $newPasswordCheck, $newPasswordErr)) {
        $errors[] = Password::getErrorMessage($newPasswordErr);
    }

    if (empty($errors)) {
        $changeset = ['password' => Password::create($newPassword)->build()];
        DB::update('user', 'id=' . User::getId(), $changeset);
        Logger::notice('user', sprintf('User "%s" has changed their password', User::getUsername()), ['user_id' => User::getId()]);
        User::refreshLogin($changeset);
        $output .= Message::ok(_lang('global.saved'));
    } else {
        $output .= Message::list($errors);
    }
}

$output .= Form::render(
    [
        'name' => 'user_settings_password',
        'table_attrs' => ' class="profiletable"',
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.password') . '</legend>',
        'form_append' => '</fieldset>'
            . Form::input('submit', 'save', _lang('mod.settings.password.submit')),
    ],
    [
        [
            'label' => _lang('mod.settings.password.current'),
            'content' => Form::input('password', 'current_password', null, ['class' => 'inputsmall', 'autocomplete' => 'off']),
        ],
        [
            'label' => _lang('mod.settings.password.new'),
            'content' => Form::input('password', 'new_password', null, ['class' => 'inputsmall', 'autocomplete' => 'new-password']),
        ],
        [
            'label' => _lang('mod.settings.password.new') . ' (' . _lang('global.check') . ')',
            'content' => Form::input('password', 'new_password_check', null, ['class' => 'inputsmall', 'autocomplete' => 'new-password']),
        ],
    ]
);
