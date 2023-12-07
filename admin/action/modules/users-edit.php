<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Extend;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';
$errno = 0;

// load user
$continue = false;

if (isset($_GET['id'])) {
    $id = Request::get('id');
    $query = DB::queryRow('SELECT u.*,g.level group_level FROM ' . DB::table('user') . ' u JOIN ' . DB::table('user_group') . ' g ON(u.group_id=g.id) WHERE u.username=' . DB::val($id));

    if ($query !== false) {
        // test access
        if (!User::equals($query['id'])) {
            if (User::checkLevel($query['id'], $query['group_level'])) {
                $continue = true;
            }
        } else {
            $_admin->redirect(Router::module('settings', ['absolute' => true]));

            return;
        }
    } else {
        $errno = 1;
    }
} else {
    $continue = true;
    $id = null;
    $query = [
        'id' => '-1',
        'group_id' => Settings::get('defaultgroup'),
        'levelshift' => 0,
        'username' => '',
        'publicname' => null,
        'blocked' => 0,
        'email' => '@',
        'avatar' => null,
        'note' => '',
        'wysiwyg' => '1',
        'public' => '1',
        'massemail' => '0',
    ];
}

if ($continue) {
    // group select
    $group_select = Admin::userSelect('group_id', [
        'selected' => isset($_POST['group_id']) ? (int) Request::post('group_id') : $query['group_id'],
        'group_cond' => 'level<' . User::getLevel(),
        'select_groups' => true,
    ]);

    // save
    if (isset($_POST['username'])) {
        $errors = [];

        // username
        $username = User::normalizeUsername(Request::post('username', ''));

        if ($username === '') {
            $errors[] = _lang('user.msg.badusername');
        } else {
            $usernamechange = false;

            if ($username !== $query['username']) {
                if (User::isNameAvailable($username, $query['id'])) {
                    $usernamechange = true;
                } else {
                    $errors[] = _lang('user.msg.userexists');
                }
            }
        }

        // publicname
        $publicname = User::normalizePublicname(Request::post('publicname', ''));

        if ($publicname !== $query['publicname']) {
            if ($publicname !== '') {
                if (!User::isNameAvailable($publicname, $query['id'])) {
                    $errors[] = _lang('user.msg.publicnameexists');
                }
            } else {
                $publicname = null;
            }
        }

        // email
        $email = trim(Request::post('email', ''));

        if (!Email::validate($email)) {
            $errors[] = _lang('user.msg.bademail');
        } elseif (
            $email != $query['email']
            && !User::isEmailAvailable($email)
        ) {
            $errors[] = _lang('user.msg.emailexists');
        }

        // wysiwyg
        $wysiwyg = Form::loadCheckbox('wysiwyg');

        // mass email
        $massemail = Form::loadCheckbox('massemail');

        // public
        $public = Form::loadCheckbox('public');

        // avatar
        if (isset($query['avatar']) && Form::loadCheckbox('removeavatar')) {
            User::removeAvatar($query['avatar']);
            $avatar = null;
        } else {
            $avatar = $query['avatar'];
        }

        // password
        $password = Request::post('password', '');

        if (($id === null || $password !== '') && !Password::validate($password, null, $password_err)) {
            $errors[] = Password::getErrorMessage($password_err);
        }

        // note
        $note = _e(trim(StringHelper::cut(Request::post('note'), 1024)));

        // blocked
        $blocked = Form::loadCheckbox('blocked');

        // group
        if (isset($_POST['group_id'])) {
            $group = (int) Request::post('group_id');
            $group_test = DB::queryRow('SELECT level FROM ' . DB::table('user_group') . ' WHERE id=' . $group . ' AND id!=' . User::GUEST_GROUP_ID . ' AND level<' . User::getLevel());

            if ($group_test !== false) {
                if ($group_test['level'] > User::getLevel()) {
                    $errors[] = _lang('global.badinput');
                }
            } else {
                $errors[] = _lang('global.badinput');
            }
        } else {
            $group = $query['group_id'];
        }

        // levelshift
        if (User::isSuperAdmin()) {
            $levelshift = Form::loadCheckbox('levelshift');
        } else {
            $levelshift = $query['levelshift'];
        }

        // save
        if (empty($errors)) {
            $changeset = [
                'email' => $email,
                'avatar' => $avatar,
                'note' => $note,
                'publicname' => $publicname,
                'group_id' => $group,
                'blocked' => $blocked,
                'levelshift' => $levelshift,
                'massemail' => $massemail,
                'public' => $public,
                'wysiwyg' => $wysiwyg,
            ];

            if ($id === null || $password !== '') {
                $changeset['password'] = Password::create($password)->build();
            }

            if ($id === null || $usernamechange) {
                $changeset['username'] = $username;
            }

            $action = ($id === null ? 'new' : 'edit');
            Extend::call('admin.user.' . $action . '.before', [
                'id' => $id,
                'user' => $id === null ? null : $query,
                'changeset' => &$changeset,
            ]);

            if ($id !== null) {
                // update
                DB::update('user', 'id=' . DB::val($query['id']), $changeset);
                Logger::notice(
                    'user',
                    sprintf('User "%s" edited via admin module', $query['username']),
                    ['diff' => array_diff_assoc($changeset, $query)]
                );
                Extend::call('user.edit', ['id' => $query['id']]);
                $_admin->redirect(Router::admin('users-edit', ['query' => ['r' => 1, 'id' => $username]]));

                return;
            }

            // insert
            $changeset += [
                'registertime' => time(),
                'activitytime' => time(),
            ];
            $id = DB::insert('user', $changeset, true);
            Logger::notice(
                'user',
                sprintf('User "%s" created via admin module', $changeset['username']),
                ['data' => $changeset]
            );
            Extend::call('user.new', ['id' => $id]);
            $_admin->redirect(Router::admin('users-edit', ['query' => ['r' => 2, 'id' => $username]]));

            return;
        }

        $message = Message::list($errors);
    }

    // messages
    $messages_code = '';

    if (isset($_GET['r'])) {
        switch (Request::get('r')) {
            case 1:
                $messages_code .= Message::ok(_lang('global.saved'));
                break;
            case 2:
                $messages_code .= Message::ok(_lang('global.created'));
                break;
        }
    }

    if ($message != '') {
        $messages_code .= $message;
    }

    // output
    $output .= '
<p class="bborder">' . _lang('admin.users.edit.p') . '</p>
' . $messages_code . '
<form autocomplete="off" action="' . _e(Router::admin('users-edit', (($id != null)) ? ['query' => ['id' => $id]] : null)) . '" method="post" name="userform">
<table class="formtable">

<tr>
<th>' . _lang('login.username') . '</th>
<td>' . Form::input('text', 'username', Request::post('username', $query['username']), ['class' => 'inputsmall', 'maxlength' => 24]) . '</td>
</tr>

<tr>
<th>' . _lang('mod.settings.account.publicname') . '</th>
<td>' . Form::input('text', 'publicname', Request::post('publicname', $query['publicname']), ['class' => 'inputsmall', 'maxlength' => 24], false) . '</td>
</tr>

<tr>
<th>' . _lang('global.email') . '</th>
<td>' . Form::input('email', 'email', Request::post('email', $query['email']), ['class' => 'inputsmall']) . '</td>
</tr>

<tr>
<th>' . _lang((($id == null) ? 'login.password' : 'mod.settings.password.new')) . '</th>
<td>' . Form::input('password', 'password', null, ['class' => 'inputsmall', 'autocomplete' => 'new-password']) . '</td>
</tr>

<tr>
<th>' . _lang('global.group') . '</th>
<td>' . $group_select . '</td>
</tr>

<tr>
<th>' . _lang('login.blocked') . '</th>
<td>' . Form::input('checkbox', 'blocked', '1', ['checked' => ($query['blocked'] || isset($_POST['blocked']))]) . '</td>
</tr>

<tr>
<th>' . _lang('global.levelshift') . '</th>
<td>' . Form::input('checkbox', 'levelshift', '1', ['checked' => ($query['levelshift'] || isset($_POST['levelshift'])), 'disabled' => !User::isSuperAdmin()]) . '</td>
</tr>

<tr>
<th>' . _lang('mod.settings.account.wysiwyg') . '</th>
<td>' . Form::input('checkbox', 'wysiwyg', '1', ['checked' => ($query['wysiwyg'] || isset($_POST['wysiwyg']))]) . '</td>
</tr>

<tr>
<th>' . _lang('mod.settings.account.massemail') . '</th>
<td>' . Form::input('checkbox', 'massemail', '1', ['checked' => ($query['massemail'] || isset($_POST['massemail']))]) . '</td>
</tr>

<tr>
<th>' . _lang('mod.settings.account.public') . '</th>
<td>' . Form::input('checkbox', 'public', '1', ['checked' => ($query['public'] || isset($_POST['public']))])  . '</td>
</tr>

<tr>
<th>' . _lang('global.avatar') . '</th>
<td><label>' . Form::input('checkbox', 'removeavatar', '1') . ' ' . _lang('global.delete') . '</label></td>
</tr>

<tr class="valign-top">
<th>' . _lang('global.note') . '</th>
<td>' . Form::textarea('note', Request::post('note', $query['note']), ['class' => 'areasmall', 'rows' => 9, 'cols' => 33], false) . '</td>
</tr>

' . Extend::buffer('admin.user.form', ['user' => $query]) . '

<tr><td></td>
<td>
    ' . Form::input('submit', null, _lang((isset($_GET['id']) ? 'global.save' : 'global.create')), ['class' => 'button bigger', 'accesskey' => 's'])
    . (($id != null) ? ' <small>' . _lang('admin.content.form.thisid') . ' ' . $query['id'] . '</small>' : '')
. '</td>
</tr>

</table>
' . Xsrf::getInput() . '</form>
';

    // link to profile
    if ($id != null) {
        $output .= '
  <p>
    <a href="' . _e(Router::module('profile', ['query' => ['id' => $query['username']]])) . '" target="_blank">' . _lang('mod.profile') . ' &gt;</a>
  </p>
  ';
    }
} else {
    switch ($errno) {
        case 1:
            $output .= Message::warning(_lang('global.baduser'));
            break;
        default:
            $output .= Message::error(_lang('global.disallowed'));
            break;
    }
}
