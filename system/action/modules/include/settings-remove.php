<?php

use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

if (isset($_POST['submit'])) {
    $errors = [];

    if (!User::checkPassword(Request::post('current_password', ''))) {
        $errors[] = _lang('mod.settings.password.error.bad_current');
    }

    if (!Form::loadCheckbox('confirm')) {
        $errors[] = _lang('mod.settings.remove.error.not_confirmed');
    }

    if (empty($errors)) {
        if (User::delete(User::getId())) {
            $_SESSION = [];
            session_destroy();

            $_index->redirect(Router::module('login', ['query' => ['login_form_result' => 4], 'absolute' => true]));

            return;
        } else {
            $output .= Message::error(_lang('mod.settings.remove.error.failed'));
        }
    } else {
        $output .= Message::list($errors);
    }
}

$output .= Form::render(
    [
        'name' => 'user_settings_remove',
        'table_attrs' => ' class="profiletable"',
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.remove') . '</legend>',
        'form_append' => '</fieldset>'
            . '<input type="submit" name="submit" value="' . _lang('mod.settings.remove.submit') . '">',
    ],
    [
        [
            'label' => _lang('mod.settings.password.current'),
            'content' => '<input type="password" name="current_password" class="inputsmall" autocomplete="off">',
        ],
        [
            'label' => '',
            'content' => '<label>'
                . '<input type="checkbox" name="confirm" value="1" onclick="if (this.checked) return Sunlight.confirm();"> '
                . _lang('mod.settings.remove.confirm', ['%username%' => User::getUsername()])
                . '</label>',
        ],
    ]
);
