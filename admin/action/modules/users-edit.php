<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Util\Password;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$message = "";
$errno = 0;

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = \Sunlight\Util\Request::get('id');
    $query = DB::queryRow("SELECT u.*,g.level group_level FROM " . _users_table . " u JOIN " . _groups_table . " g ON(u.group_id=g.id) WHERE u.username=" . DB::val($id));
    if ($query !== false) {

        // test pristupu
        if ($query['id'] != _user_id) {
            if (\Sunlight\User::checkLevel($query['id'], $query['group_level'])) {
                if ($query['id'] != 0) {
                    $continue = true;
                } else {
                    $errno = 2;
                }
            }
        } else {
            $admin_redirect_to = \Sunlight\Router::module('settings');

            return;
        }

    } else {
        $errno = 1;
    }
} else {
    $continue = true;
    $id = null;
    $query = array(
        'id' => '-1',
        'group_id' => _defaultgroup,
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
    );
}

if ($continue) {
    
    // vyber skupiny
    $group_select = \Sunlight\Admin\Admin::userSelect('group_id', (isset($_POST['group_id']) ? (int) \Sunlight\Util\Request::post('group_id') : $query['group_id']), "id!=2 AND level<" . _priv_level, null, null, true);

    /* ---  ulozeni  --- */
    if (isset($_POST['username'])) {

        $errors = array();

        // nacteni a kontrola promennych

        // username
        $username = \Sunlight\Util\Request::post('username');
        if (mb_strlen($username) > 24) {
            $username = mb_substr($username, 0, 24);
        }
        $username = \Sunlight\Util\StringManipulator::slugify($username, false);
        if ($username == "") {
            $errors[] = _lang('user.msg.badusername');
        } else {
            $usernamechange = false;
            if ($username != $query['username']) {
                if (DB::count(_users_table, '(username=' . DB::val($username) . ' OR publicname=' . DB::val($username) . ') AND id!=' . DB::val($query['id'])) === 0) {
                    $usernamechange = true;
                } else {
                    $errors[] = _lang('user.msg.userexists');
                }
            }
        }

        // publicname
        $publicname = _e(\Sunlight\Util\StringManipulator::trimExtraWhitespace(\Sunlight\Util\Request::post('publicname')));
        if (mb_strlen($publicname) > 24) {
            $errors[] = _lang('user.msg.publicnametoolong');
        } elseif ($publicname != $query['publicname'] && $publicname != "") {
            if (DB::count(_users_table, '(publicname=' . DB::val($publicname) . ' OR username=' . DB::val($publicname) . ') AND id!=' . DB::val($query['id'])) !== 0) {
                $errors[] = _lang('user.msg.publicnameexists');
            }
        }
        if ($publicname === '') {
            $publicname = null;
        }

        // email
        $email = trim(\Sunlight\Util\Request::post('email'));
        if (!\Sunlight\Email::validate($email)) {
            $errors[] = _lang('user.msg.bademail');
        } else {
            if ($email != $query['email']) {
                if (DB::count(_users_table, 'email=' . DB::val($email) . ' AND id!=' . DB::val($query['id'])) !== 0) {
                    $errors[] = _lang('user.msg.emailexists');
                }
            }
        }

        // wysiwyg
        $wysiwyg = \Sunlight\Util\Form::loadCheckbox('wysiwyg');

        // hromadny email
        $massemail = \Sunlight\Util\Form::loadCheckbox('massemail');

        // verejny profil
        $public = \Sunlight\Util\Form::loadCheckbox('public');

        // avatar
        if (isset($query['avatar']) && \Sunlight\Util\Form::loadCheckbox("removeavatar")) {
            @unlink(_root . 'images/avatars/' . $query['avatar'] . '.jpg');
            $avatar = null;
        } else {
            $avatar = $query['avatar'];
        }

        // password
        $passwordchange = false;
        $password = \Sunlight\Util\Request::post('password');
        if ($id == null && $password == "") {
            $errors[] = _lang('admin.users.edit.passwordneeded');
        }
        if ($password != "") {
            $passwordchange = true;
            $password = Password::create($password)->build();
        }

        // note
        $note = _e(trim(\Sunlight\Util\StringManipulator::cut(\Sunlight\Util\Request::post('note'), 1024)));

        // blocked
        $blocked = \Sunlight\Util\Form::loadCheckbox("blocked");

        // group
        if (isset($_POST['group_id'])) {
            $group = (int) \Sunlight\Util\Request::post('group_id');
            $group_test = DB::queryRow("SELECT level FROM " . _groups_table . " WHERE id=" . $group . " AND id!=2 AND level<" . _priv_level);
            if ($group_test !== false) {
                if ($group_test['level'] > _priv_level) {
                    $errors[] = _lang('global.badinput');
                }
            } else {
                $errors[] = _lang('global.badinput');
            }
        } else {
            $group = $query['group_id'];
        }

        // levelshift
        if (_user_id == _super_admin_id) {
            $levelshift = \Sunlight\Util\Form::loadCheckbox('levelshift');
        } else {
            $levelshift = $query['levelshift'];
        }

        // ulozeni / vytvoreni anebo seznam chyb
        if (count($errors) == 0) {

            // changeset
            $changeset = array(
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
            );
            if ($id === null || $passwordchange) {
                $changeset['password'] = $password;
            }
            if ($id === null || $usernamechange) {
                $changeset['username'] = $username;
            }

            $action = ($id === null ? 'new' : 'edit');
            Extend::call('admin.user.' . $action . '.before', array(
                'id' => $id,
                'user' => $id === null ? null : $query,
                'changeset' => &$changeset,
            ));

            if ($id !== null) {
                // uprava
                DB::update(_users_table, 'id=' . DB::val($query['id']), $changeset);
                Extend::call('user.edit', array('id' => $query['id'], 'username' => $username, 'email' => $email));
                $admin_redirect_to = 'index.php?p=users-edit&r=1&id=' . $username;

                return;
            } else {
                // vytvoreni
                $changeset += array(
                    'registertime' => time(),
                    'activitytime' => time(),
                );
                $id = DB::insert(_users_table, $changeset, true);
                Extend::call('user.new', array('id' => $id, 'username' => $username, 'email' => $email));
                $admin_redirect_to = 'index.php?p=users-edit&r=2&id=' . $username;

                return;
            }

        } else {
            $message = \Sunlight\Message::renderList($errors, 'errors');
        }

    }

    /* ---  vystup  --- */

    // zpravy
    $messages_code = "";

    if (isset($_GET['r'])) {
        switch (\Sunlight\Util\Request::get('r')) {
            case 1:
                $messages_code .= \Sunlight\Message::render(_msg_ok, _lang('global.saved'));
                break;
            case 2:
                $messages_code .= \Sunlight\Message::render(_msg_ok, _lang('global.created'));
                break;
        }
    }

    if ($message != "") {
        $messages_code .= \Sunlight\Message::render(_msg_warn, $message);
    }

    $output .= "
<p class='bborder'>" . _lang('admin.users.edit.p') . "</p>
" . $messages_code . "
<form autocomplete='off' action='index.php?p=users-edit" . (($id != null) ? "&amp;id=" . $id : '') . "' method='post' name='userform'>
<table class='formtable'>

<tr>
<th>" . _lang('login.username') . "</th>
<td><input type='text' class='inputsmall'" . \Sunlight\Util\Form::restorePostValueAndName('username', $query['username']) . " maxlength='24'></td>
</tr>

<tr>
<th>" . _lang('mod.settings.publicname') . "</th>
<td><input type='text' class='inputsmall'" . \Sunlight\Util\Form::restorePostValueAndName('publicname', $query['publicname'], true) . " maxlength='24'></td>
</tr>

<tr>
<th>" . _lang((($id == null) ? 'login.password' : 'mod.settings.password.new')) . "</th>
<td><input type='password' name='password' class='inputsmall'></td>
</tr>

<tr>
<th>" . _lang('global.group') . "</th>
<td>" . $group_select . "</td>
</tr>

<tr>
<th>" . _lang('login.blocked') . "</th>
<td><input type='checkbox' name='blocked' value='1'" . \Sunlight\Util\Form::activateCheckbox($query['blocked'] || isset($_POST['blocked'])) . "></td>
</tr>

<tr>
<th>" . _lang('global.levelshift') . "</th>
<td><input type='checkbox' name='levelshift' value='1'" . \Sunlight\Util\Form::activateCheckbox($query['levelshift'] || isset($_POST['levelshift'])) . \Sunlight\Util\Form::disableInputUnless(_user_id == _super_admin_id) . "></td>
</tr>

<tr>
<th>" . _lang('mod.settings.wysiwyg') . "</th>
<td><input type='checkbox' name='wysiwyg' value='1'" . \Sunlight\Util\Form::activateCheckbox($query['wysiwyg'] || isset($_POST['wysiwyg'])) . "></td>
</tr>

<tr>
<th>" . _lang('mod.settings.massemail') . "</th>
<td><input type='checkbox' name='massemail' value='1'" . \Sunlight\Util\Form::activateCheckbox($query['massemail'] || isset($_POST['massemail'])) . "></td>
</tr>

<tr>
<th>" . _lang('mod.settings.public') . "</th>
<td><input type='checkbox' name='public' value='1'" . \Sunlight\Util\Form::activateCheckbox($query['public'] || isset($_POST['public'])) . "></td>
</tr>

<tr>
<th>" . _lang('global.email') . "</th>
<td><input type='email' class='inputsmall'" . \Sunlight\Util\Form::restorePostValueAndName('email', $query['email']) . "></td>
</tr>

<tr>
<th>" . _lang('global.avatar') . "</th>
<td><label><input type='checkbox' name='removeavatar' value='1'> " . _lang('mod.settings.avatar.remove') . "</label></td>
</tr>

<tr class='valign-top'>
<th>" . _lang('global.note') . "</th>
<td><textarea class='areasmall' rows='9' cols='33' name='note'>" . \Sunlight\Util\Form::restorePostValue('note', $query['note'], false, false) . "</textarea></td>
</tr>

" . Extend::buffer('admin.user.form', array('user' => $query)) . "

<tr><td></td>
<td><input type='submit' class='button bigger' value='" . _lang((isset($_GET['id']) ? 'global.save' : 'global.create')) . "' accesskey='s'>" . (($id != null) ? " <small>" . _lang('admin.content.form.thisid') . " " . $query['id'] . "</small>" : '') . "</td>
</tr>

</table>
" . \Sunlight\Xsrf::getInput() . "</form>
";

    // odkaz na profil a zjisteni ip
    if ($id != null) {
        $output .= "
  <p>
    <a href='" . \Sunlight\Router::module('profile', 'id=' . $query['username']) . "' target='_blank'>" . _lang('mod.settings.profilelink') . " &gt;</a>
  </p>
  ";
    }

} else {
    switch ($errno) {
        case 1:
            $output .= \Sunlight\Message::render(_msg_warn, _lang('global.baduser'));
            break;
        case 2:
            $output .= \Sunlight\Message::render(_msg_warn, _lang('global.rootnote'));
            break;
        default:
            $output .= \Sunlight\Message::render(_msg_err, _lang('global.disallowed'));
            break;
    }
}
