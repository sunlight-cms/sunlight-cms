<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Plugin\PluginManager;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;

defined('_root') or exit;

if (isset($_POST['save'])) {
    $errors = [];
    $changeset = [];

    // username
    $username = User::normalizeUsername(Request::post('username', ''));

    do {
        if ($username === '') {
            $errors[] = _lang('user.msg.badusername');
            break;
        }

        if ($username === User::getUsername()) {
            break;
        }

        if (!User::hasPrivilege('changeusername') &&  mb_strtolower($username) !== mb_strtolower(User::getUsername())) {
            $errors[] = _lang('mod.settings.account.username.case_error');
            break;
        }

        if (!User::isNameAvailable($username, User::getId())) {
            $errors[] = _lang('user.msg.userexists');
            break;
        }

        $changeset['username'] = $username;
    } while(false);

    // publicname
    $publicname = User::normalizePublicname(Request::post('publicname', ''));

    if ($publicname === '') {
        $publicname = null;
    }

    do {
        if ($publicname === User::$data['publicname']) {
            break;
        }

        if ($publicname === null) {
            $changeset['publicname'] = null;
            break;
        }

        if (!User::isNameAvailable($publicname, User::getId())) {
            $errors[] = _lang('user.msg.publicnameexists');
            break;
        }

        $changeset['publicname'] = $publicname;
    } while(false);

    // email
    $email = trim(Request::post('email', ''));

    do {
        if ($email === User::$data['email']) {
            break;
        }

        if (!Email::validate($email)) {
            $errors[] = _lang('user.msg.bademail');
            break;
        }

        if (!User::isEmailAvailable($email)) {
            $errors[] = _lang('user.msg.emailexists');
            break;
        }

        if (!Password::load(User::$data['password'])->match(Request::post('current_password', ''))) {
            $errors[] = _lang('mod.settings.password.error.bad_current');
            break;
        }

        $changeset['email'] = $email;
    } while(false);

    // language
    if (Settings::get('language_allowcustom')) {
        $language = Request::post('language', '');

        if ($language === '' || !Core::$pluginManager->has(PluginManager::LANGUAGE, $language)) {
            $language = null;
        }

        if ($language !== User::$data['language']) {
            $changeset['language'] = $language;
        }
    }

    // public
    $public = Form::loadCheckbox('public');

    if ($public != User::$data['public']) {
        $changeset['public'] = $public;
    }
    
    // massemail
    $massemail = Form::loadCheckbox('massemail');

    if ($massemail != User::$data['massemail']) {
        $changeset['massemail'] = $massemail;
    }
    
    // wysiwyg
    if (User::hasPrivilege('administration')) {
        $wysiwyg = Form::loadCheckbox('wysiwyg');

        if ($wysiwyg != User::$data['wysiwyg']) {
            $changeset['wysiwyg'] = $wysiwyg;
        }
    }

    // process
    Extend::call('mod.settings.account.submit', [
        'changeset' => &$changeset,
        'errors' => &$errors,
    ]);

    if (empty($errors)) {
        Extend::call('mod.settings.account.save', ['changeset' => &$changeset]);
        DB::update('user', 'id=' . User::getId(), $changeset);
        Extend::call('user.edit', ['id' => User::getId()]);

        $_index['type'] = _index_redir;
        $_index['redirect_to'] = Router::module('settings', 'action=account&saved', true);

        return;
    } else {
        $output .= Message::list($errors);
    }
} elseif (isset($_GET['saved'])) {
    $output .= Message::ok(_lang('global.saved'));
}

$output .= Form::render(
    [
        'name' => 'user_settings_account',
        'table_attrs' => ' class="profiletable"',
        'submit_row' => [],
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.account') . '</legend>',
        'form_append' => '</fieldset>'
            . '<input type="submit" name="save" value="' . _lang('global.savechanges') . '">' . "\n"
            . '<input type="reset" value="' . _lang('global.reset') . '" onclick="return Sunlight.confirm();">',
    ],
    [
        [
            'label' => _lang('login.username'),
            'content' => '<input type="text" maxlength="24" class="inputsmall" autocomplete="username"' . Form::restorePostValueAndName('username', User::getUsername()) . '>'
                . (User::hasPrivilege('changeusername') ? '' : ' <span class="hint">(' . _lang('mod.settings.account.username.case_only') . ')</span>'),
        ],
        [
            'label' => _lang('mod.settings.account.publicname'),
            'content' => '<input type="text" maxlength="24" class="inputsmall"' . Form::restorePostValueAndName('publicname', User::$data['publicname'], false) . '>'
                . ' <span class="hint">(' . _lang('mod.settings.account.publicname.hint') . ')</span>',
        ],
        [
            'label' => _lang('global.email'),
            'content' => '<input type="email" maxlength="191" class="inputsmall"' . Form::restorePostValueAndName('email', User::$data['email']) . '>',
        ],
        [
            'label' => _lang('mod.settings.password.current'),
            'content' => '<input type="password" name="current_password" class="inputsmall" autocomplete="off">'
                . ' <span class="hint">(' . _lang('mod.settings.account.current_password.hint') . ')</span>',
        ],
        Settings::get('language_allowcustom')
            ? [
                'label' => _lang('global.language'),
                'content' => '<select name="language" class="inputsmall">'
                    . '<option value="">' . _lang('global.default') . '</option>'
                    . Core::$pluginManager->select(PluginManager::LANGUAGE, User::$data['language'])
                    . '</select>'
            ]
            : [],
        [
            'label' => _lang('mod.settings.account.public'),
            'content' => '<label>'
                . '<input type="checkbox" name="public" value="1"' . Form::restoreChecked('saved', 'public', User::$data['public']). '> '
                . _lang('mod.settings.account.public.label')
                . '</label>',
        ],
        [
            'label' => _lang('mod.settings.account.massemail'),
            'content' => '<label>'
                . '<input type="checkbox" name="massemail" value="1"' . Form::restoreChecked('saved', 'massemail', User::$data['massemail']). '> '
                . _lang('mod.settings.account.massemail.label')
                . '</label>',
        ],
        User::hasPrivilege('administration')
            ? [
                'label' => _lang('mod.settings.account.wysiwyg'),
                'content' => '<label>'
                    . '<input type="checkbox" name="wysiwyg" value="1"' . Form::restoreChecked('saved', 'wysiwyg', User::$data['wysiwyg']). '> '
                    . _lang('mod.settings.account.wysiwyg.label')
                    . '</label>',
            ]
            : [],
    ]
);
