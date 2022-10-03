<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
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

    if ($newPassword !== $newPasswordCheck) {
        $errors[] = _lang('mod.settings.password.error.not_same');
    }

    if ($newPassword === '') {
        $errors[] = _lang('mod.settings.password.error.empty');
    }

    if (Password::isPasswordTooLong($newPassword)) {
        $errors[] = _lang('mod.settings.password.error.too_long');
    }

    if (empty($errors)) {
        $builtNewPassword = Password::create($newPassword)->build();
        DB::update('user', 'id=' . User::getId(), ['password' => $builtNewPassword]);
        $_SESSION['user_auth'] = User::getAuthHash(User::AUTH_SESSION, User::$data['email'], $builtNewPassword);
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
            . '<input type="submit" name="save" value="' . _lang('mod.settings.password.submit') . '">',
    ],
    [
        [
            'label' => _lang('mod.settings.password.current'),
            'content' => '<input type="password" name="current_password" class="inputsmall" autocomplete="off">',
        ],
        [
            'label' => _lang('mod.settings.password.new'),
            'content' => '<input type="password" name="new_password" class="inputsmall" autocomplete="new-password">',
        ],
        [
            'label' => _lang('mod.settings.password.new') . ' (' . _lang('global.check') . ')',
            'content' => '<input type="password" name="new_password_check" class="inputsmall" autocomplete="new-password">',
        ],
    ]
);
