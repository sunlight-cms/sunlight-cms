<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Picture;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$message = "";
$errno = 0;

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = Request::get('id');
    $query = DB::queryRow("SELECT u.*,g.level group_level FROM " . _user_table . " u JOIN " . _user_group_table . " g ON(u.group_id=g.id) WHERE u.username=" . DB::val($id));
    if ($query !== false) {

        // test pristupu
        if ($query['id'] != _user_id) {
            if (User::checkLevel($query['id'], $query['group_level'])) {
                if ($query['id'] != 0) {
                    $continue = true;
                } else {
                    $errno = 2;
                }
            }
        } else {
            $admin_redirect_to = Router::module('settings', null, true);

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
    $group_select = Admin::userSelect('group_id', (isset($_POST['group_id']) ? (int) Request::post('group_id') : $query['group_id']), "id!=2 AND level<" . _priv_level, null, null, true);

    /* ---  ulozeni  --- */
    if (isset($_POST['username'])) {

        $errors = array();

        // nacteni a kontrola promennych

        // username
        $username = Request::post('username');
        if (mb_strlen($username) > 24) {
            $username = mb_substr($username, 0, 24);
        }
        $username = StringManipulator::slugify($username, false);
        if ($username == "") {
            $errors[] = _lang('user.msg.badusername');
        } else {
            $usernamechange = false;
            if ($username != $query['username']) {
                if (DB::count(_user_table, '(username=' . DB::val($username) . ' OR publicname=' . DB::val($username) . ') AND id!=' . DB::val($query['id'])) === 0) {
                    $usernamechange = true;
                } else {
                    $errors[] = _lang('user.msg.userexists');
                }
            }
        }

        // publicname
        $publicname = _e(StringManipulator::trimExtraWhitespace(Request::post('publicname')));
        if (mb_strlen($publicname) > 24) {
            $errors[] = _lang('user.msg.publicnametoolong');
        } elseif ($publicname != $query['publicname'] && $publicname != "") {
            if (DB::count(_user_table, '(publicname=' . DB::val($publicname) . ' OR username=' . DB::val($publicname) . ') AND id!=' . DB::val($query['id'])) !== 0) {
                $errors[] = _lang('user.msg.publicnameexists');
            }
        }
        if ($publicname === '') {
            $publicname = null;
        }

        // email
        $email = trim(Request::post('email'));
        if (!Email::validate($email)) {
            $errors[] = _lang('user.msg.bademail');
        } else {
            if ($email != $query['email']) {
                if (DB::count(_user_table, 'email=' . DB::val($email) . ' AND id!=' . DB::val($query['id'])) !== 0) {
                    $errors[] = _lang('user.msg.emailexists');
                }
            }
        }

        // wysiwyg
        $wysiwyg = Form::loadCheckbox('wysiwyg');

        // hromadny email
        $massemail = Form::loadCheckbox('massemail');

        // verejny profil
        $public = Form::loadCheckbox('public');

        // avatar
        if (isset($query['avatar']) && Form::loadCheckbox("removeavatar")) {
            @unlink(Picture::get('images/avatars/', $query['avatar'], 'jpg', 1));
            $avatar = null;
        } else {
            $avatar = $query['avatar'];
        }

        // password
        $passwordchange = false;
        $password = Request::post('password');
        if ($id == null && $password == "") {
            $errors[] = _lang('admin.users.edit.passwordneeded');
        }
        if ($password != "") {
            $passwordchange = true;
            $password = Password::create($password)->build();
        }

        // note
        $note = _e(trim(StringManipulator::cut(Request::post('note'), 1024)));

        // blocked
        $blocked = Form::loadCheckbox("blocked");

        // group
        if (isset($_POST['group_id'])) {
            $group = (int) Request::post('group_id');
            $group_test = DB::queryRow("SELECT level FROM " . _user_group_table . " WHERE id=" . $group . " AND id!=2 AND level<" . _priv_level);
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
            $levelshift = Form::loadCheckbox('levelshift');
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
                DB::update(_user_table, 'id=' . DB::val($query['id']), $changeset);
                Extend::call('user.edit', array('id' => $query['id'], 'username' => $username, 'email' => $email));
                $admin_redirect_to = 'index.php?p=users-edit&r=1&id=' . $username;

                return;
            } else {
                // vytvoreni
                $changeset += array(
                    'registertime' => time(),
                    'activitytime' => time(),
                );
                $id = DB::insert(_user_table, $changeset, true);
                Extend::call('user.new', array('id' => $id, 'username' => $username, 'email' => $email));
                $admin_redirect_to = 'index.php?p=users-edit&r=2&id=' . $username;

                return;
            }

        } else {
            $message = Message::renderList($errors, 'errors');
        }

    }

    /* ---  vystup  --- */

    // zpravy
    $messages_code = "";

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

    if ($message != "") {
        $messages_code .= Message::warning($message, true);
    }

    $output .= "
<p class='bborder'>" . _lang('admin.users.edit.p') . "</p>
" . $messages_code . "
<form autocomplete='off' action='index.php?p=users-edit" . (($id != null) ? "&amp;id=" . $id : '') . "' method='post' name='userform'>
<table class='formtable'>

<tr>
<th>" . _lang('login.username') . "</th>
<td><input type='text' class='inputsmall'" . Form::restorePostValueAndName('username', $query['username']) . " maxlength='24'></td>
</tr>

<tr>
<th>" . _lang('mod.settings.publicname') . "</th>
<td><input type='text' class='inputsmall'" . Form::restorePostValueAndName('publicname', $query['publicname'], true) . " maxlength='24'></td>
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
<td><input type='checkbox' name='blocked' value='1'" . Form::activateCheckbox($query['blocked'] || isset($_POST['blocked'])) . "></td>
</tr>

<tr>
<th>" . _lang('global.levelshift') . "</th>
<td><input type='checkbox' name='levelshift' value='1'" . Form::activateCheckbox($query['levelshift'] || isset($_POST['levelshift'])) . Form::disableInputUnless(_user_id == _super_admin_id) . "></td>
</tr>

<tr>
<th>" . _lang('mod.settings.wysiwyg') . "</th>
<td><input type='checkbox' name='wysiwyg' value='1'" . Form::activateCheckbox($query['wysiwyg'] || isset($_POST['wysiwyg'])) . "></td>
</tr>

<tr>
<th>" . _lang('mod.settings.massemail') . "</th>
<td><input type='checkbox' name='massemail' value='1'" . Form::activateCheckbox($query['massemail'] || isset($_POST['massemail'])) . "></td>
</tr>

<tr>
<th>" . _lang('mod.settings.public') . "</th>
<td><input type='checkbox' name='public' value='1'" . Form::activateCheckbox($query['public'] || isset($_POST['public'])) . "></td>
</tr>

<tr>
<th>" . _lang('global.email') . "</th>
<td><input type='email' class='inputsmall'" . Form::restorePostValueAndName('email', $query['email']) . "></td>
</tr>

<tr>
<th>" . _lang('global.avatar') . "</th>
<td><label><input type='checkbox' name='removeavatar' value='1'> " . _lang('mod.settings.avatar.remove') . "</label></td>
</tr>

<tr class='valign-top'>
<th>" . _lang('global.note') . "</th>
<td><textarea class='areasmall' rows='9' cols='33' name='note'>" . Form::restorePostValue('note', $query['note'], false, false) . "</textarea></td>
</tr>

" . Extend::buffer('admin.user.form', array('user' => $query)) . "

<tr><td></td>
<td><input type='submit' class='button bigger' value='" . _lang((isset($_GET['id']) ? 'global.save' : 'global.create')) . "' accesskey='s'>" . (($id != null) ? " <small>" . _lang('admin.content.form.thisid') . " " . $query['id'] . "</small>" : '') . "</td>
</tr>

</table>
" . Xsrf::getInput() . "</form>
";

    // odkaz na profil a zjisteni ip
    if ($id != null) {
        $output .= "
  <p>
    <a href='" . _e(Router::module('profile', 'id=' . $query['username'])) . "' target='_blank'>" . _lang('mod.settings.profilelink') . " &gt;</a>
  </p>
  ";
    }

} else {
    switch ($errno) {
        case 1:
            $output .= Message::warning(_lang('global.baduser'));
            break;
        case 2:
            $output .= Message::warning(_lang('global.rootnote'));
            break;
        default:
            $output .= Message::error(_lang('global.disallowed'));
            break;
    }
}
