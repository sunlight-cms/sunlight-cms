<?php

use Sunlight\Extend;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\UserData;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\Response;

defined('_root') or exit;

if (isset($_POST['download'])) {
    $errors = [];
    $options = [];

    // check current password
    if (!Password::load(User::$data['password'])->match(Request::post('current_password', ''))) {
        $errors[] = _lang('mod.settings.password.error.bad_current');
    }

    // antispam
    if (!IpLog::check(IpLog::ANTI_SPAM)) {
        $errors[] = _lang('misc.antispam_error', ["%antispamtimeout%" => Settings::get('antispamtimeout')]);
    }

    // process
    Extend::call('mod.settings.download.submit', [
        'errors' => &$errors,
        'options' => &$options,
    ]);

    if (empty($errors)) {
        IpLog::update(IpLog::ANTI_SPAM);
        $tmpFile = (new UserData(User::getId(), $options))->generate();
        Response::downloadFile($tmpFile->getPathname(), sprintf('%s-%s.zip', User::getUsername(), date('Y-m-d')));
        $tmpFile->discard();

        return;
    } else {
        $output .= Message::list($errors);
    }
}

$output .= '<p>' . _lang('mod.settings.download.info') . '</p>';

$output .= Form::render(
    [
        'name' => 'user_settings_download',
        'table_attrs' => ' class="profiletable"',
        'submit_row' => [],
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.download') . '</legend>',
        'form_append' => '</fieldset>'
            . '<input type="submit" name="download" value="' . _lang('mod.settings.download.submit') . '">',
    ],
    [
        [
            'label' => _lang('mod.settings.password.current'),
            'content' => '<input type="password" name="current_password" class="inputsmall" autocomplete="off">',
        ],
    ]
);
