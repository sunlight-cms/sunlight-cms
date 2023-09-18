<?php

use Sunlight\Email;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Database\Database as DB;

defined('SL_ROOT') or exit;

$_index->title = _lang('mod.massemail.title');

// get and check params
$valid = false;

do {
    $email = Request::get('email', '');

    if (!Email::validate($email)) {
        break;
    }

    $user = DB::queryRow('SELECT id,email,password,massemail FROM ' . DB::table('user') . ' WHERE email=' . DB::val($email));

    if ($user === false) {
        break;
    }

    $key = Request::get('key', '');

    if (User::getAuthHash(User::AUTH_MASSEMAIL, $user['email'], $user['password']) !== $key) {
        break;
    }

    $valid = true;
} while (false);

// show error if not valid
if (!$valid) {
    $output .= Message::warning(
        _lang(
            'mod.massemail.invalid_link',
            ['%settings_link%' => _e(Router::module('settings', ['query' => ['action' => 'account']]))]
        ),
        true
    );

    return;
}

// module logic
if (isset($_POST['save'])) {
    $changeset = ['massemail' => Form::loadCheckbox('massemail')];
    Extend::call('mod.massemail.submit', ['changeset' => &$changeset]);
    DB::update('user', 'id=' . $user['id'], $changeset);

    $_index->redirect(Router::module('massemail', ['query' => ['email' => $email, 'key' => $key, 'saved' => '1']]));

    return;
}

if (isset($_GET['saved'])) {
    $output .= Message::ok(_lang('global.saved'));
}

$output .= Form::render(
    [
        'name' => 'mod_massemail',
    ],
    [
        [
            'content' => '<label>'
                . '<input type="checkbox" name="massemail" value="1"' . Form::activateCheckbox($user['massemail']). '> '
                . _lang('mod.settings.account.massemail.label')
                . '</label>',
        ],
        Form::getSubmitRow([
            'label' => null,
            'name' => 'save',
            'text' => _lang('global.save'),
        ]),
    ]
);
