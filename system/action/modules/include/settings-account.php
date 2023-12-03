<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

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
    } while (false);

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
    } while (false);

    // language
    if (Settings::get('language_allowcustom')) {
        $language = Request::post('language', '');

        if ($language === '' || !Core::$pluginManager->getPlugins()->hasLanguage($language)) {
            $language = '';
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

        if (!empty($changeset)) {
            Logger::notice(
                'user',
                sprintf('User "%s" has changed their settings', User::getUsername()),
                ['user_id' => User::getId(), 'changeset' => $changeset]
            );
        }

        $_index->redirect(Router::module('settings', ['query' => ['action' => 'account', 'saved' => 1], 'absolute' => true]));

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
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.account') . '</legend>',
        'form_append' => '</fieldset>'
            . Form::input('submit', 'save', _lang('global.savechanges')) . "\n"
            . Form::input('reset', null, _lang('global.reset'), ['onclick' => 'return Sunlight.confirm();']),
    ],
    [
        [
            'label' => _lang('login.username'),
            'content' => Form::input('text', 'username', Request::post('username', User::getUsername()), ['class' => 'inputsmall', 'maxlength' => 24, 'autocomplete' => 'username'])
                . (User::hasPrivilege('changeusername') ? '' : ' <span class="hint">(' . _lang('mod.settings.account.username.case_only') . ')</span>'),
        ],
        [
            'label' => _lang('mod.settings.account.publicname'),
            'content' => Form::input('text', 'publicname', Request::post('public', User::$data['publicname']), ['class' => 'inputsmall', 'maxlength' => 24], false)
                . ' <span class="hint">(' . _lang('mod.settings.account.publicname.hint') . ')</span>',
        ],
        Settings::get('language_allowcustom')
            ? [
                'label' => _lang('global.language'),
                'content' => Form::select(
                    'language',
                    ['' => _lang('global.default')] + Core::$pluginManager->choices('language'),
                    User::$data['language'],
                    ['class' => 'inputsmall']
                ),
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
