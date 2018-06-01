<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava  --- */

$message = "";

$selectTime = function ($name) {
    $opts = array(1, 2, 4, 8, 25, 52, 104);
    $active = (isset($_POST[$name]) ? (int) Request::post($name) : 25);
    $output = "<select name='" . $name . "'>\n";
    for($i = 0; isset($opts[$i]); ++$i) {
        $output .= "<option value='" . $opts[$i] . "'" . (($active === $opts[$i]) ? " selected" : '') . ">" . _lang('admin.other.cleanup.time.' . $opts[$i]) . "</option>\n";
    }
    $output .= "</select>\n";

    return $output;
};

/* ---  akce  --- */

if (isset($_POST['action'])) {

    switch (Request::post('action')) {

            // cistka
        case 1:

            // nahled ci smazani?
            if (isset($_POST['do_cleanup'])) {
                $prev = false;
            } else {
                $prev = true;
                $prev_count = array();
            }

            // vzkazy
            $messages = Request::post('messages');
            switch ($messages) {

                case 1:
                    $messages_time = time() - (Request::post('messages-time') * 7 * 24 * 60 * 60);
                    if ($prev) {
                        $prev_count['mod.messages'] = DB::count(_pm_table, 'update_time<' . $messages_time);
                    } else {
                        DB::query("DELETE " . _pm_table . ",post FROM " . _pm_table . " LEFT JOIN " . _posts_table . " AS post ON (post.type=" . _post_pm . " AND post.home=" . _pm_table . ".id) WHERE update_time<" . $messages_time);
                    }
                    break;

                case 2:
                    if ($prev) {
                        $prev_count['mod.messages'] = DB::count(_posts_table, 'type=' . _post_pm);
                    } else {
                        DB::query("TRUNCATE TABLE " . _pm_table);
                        DB::delete(_posts_table, 'type=' . _post_pm);
                    }
                    break;

            }

            // komentare, prispevky, iplog
            if (Form::loadCheckbox("comments")) {
                if ($prev) {
                    $prev_count['admin.settings.functions.comments'] = DB::count(_posts_table, 'type=' . _post_section_comment . ' OR type=' . _post_article_comment);
                } else {
                    DB::delete(_posts_table, 'type=' . _post_section_comment . ' OR type=' . _post_article_comment);
                }
            }
            if (Form::loadCheckbox("posts")) {
                if ($prev) {
                    $prev_count['global.posts'] = DB::count(_posts_table, 'type IN(' . DB::arr(array(_post_book_entry, _post_shoutbox_entry, _post_forum_topic)) . ')');
                } else {
                    DB::deleteSet(_posts_table, 'type', array(
                        _post_book_entry,
                        _post_shoutbox_entry,
                        _post_forum_topic
                    ));
                }
            }
            if (Form::loadCheckbox("plugin_posts")) {
                if ($prev) {
                    $prev_count['admin.other.cleanup.other.plugin_posts.label'] = DB::count(_posts_table, 'type=' . _post_plugin);
                } else {
                    DB::delete(_posts_table, 'type=' . _post_plugin);
                }
            }
            if (Form::loadCheckbox("iplog")) {
                if ($prev) {
                    $prev_count['admin.iplog'] = DB::count(_iplog_table);
                } else {
                    DB::query("TRUNCATE TABLE " . _iplog_table);
                }
            }
            if (Form::loadCheckbox("user_activation")) {
                if ($prev) {
                    $prev_count['mod.reg.confirm'] = DB::count(_user_activation_table);
                } else {
                    DB::query("TRUNCATE TABLE " . _user_activation_table);
                }
            }

            // uzivatele
            if (Form::loadCheckbox("users")) {

                $users_time = time() - (Request::post('users-time') * 7 * 24 * 60 * 60);
                $users_group = (int) Request::post('users-group');
                if ($users_group == -1) {
                    $users_group = "";
                } else {
                    $users_group = " AND group_id=" . $users_group;
                }

                if ($prev) {
                    $prev_count['admin.users.users'] = DB::count(_users_table, 'id!=0 AND activitytime<' . $users_time . $users_group);
                } else {
                    $userids = DB::query("SELECT id FROM " . _users_table . " WHERE id!=0 AND activitytime<" . $users_time . $users_group);
                    while($userid = DB::row($userids)) {
                        User::delete($userid['id']);
                    }
                    DB::free($userids);
                }

            }

            // udrzba
            if (Form::loadCheckbox('maintenance') && !$prev) {
                Extend::call('cron.maintenance', array(
                    'last' => null,
                    'name' => 'maintenance',
                    'seconds' => null,
                    'delay' => null,
                ));
            }

            // optimalizace
            if (Form::loadCheckbox('optimize') && !$prev) {
                foreach (DB::getTablesByPrefix() as $table) {
                    DB::query('OPTIMIZE TABLE `' . $table . '`');
                }
            }

            // zprava
            if ($prev) {
                if (empty($prev_count)) {
                    $message = Message::render(_msg_warn, _lang('global.noaction'));
                    break;
                }
                $message = "<br><ul>\n";
                foreach($prev_count as $key => $val) {
                    $message .= "<li><strong>" . _lang($key) . ":</strong> <code>" . $val . "</code></li>\n";
                }
                $message .= "</ul>";
            } else {
                $message = Message::render(_msg_ok, _lang('global.done'));
            }

            break;

            // deinstalace
        case 2:
            $pass = Request::post('pass');
            $confirm = Form::loadCheckbox("confirm");
            if ($confirm) {
                $right_pass = DB::queryRow("SELECT password FROM " . _users_table . " WHERE id=0");
                if (Password::load($right_pass['password'])->match($pass)) {

                    // odhlaseni
                    User::logout();

                    // odstraneni tabulek
                    DatabaseLoader::dropTables(DB::getTablesByPrefix());

                    // zprava
                    echo "<h1>" . _lang('global.done') . "</h1>\n<p>" . _lang('admin.other.cleanup.uninstall.done') . "</p>";
                    exit;

                } else {
                    $message = Message::render(_msg_warn, _lang('admin.other.cleanup.uninstall.badpass'));
                }
            }
            break;

    }

}

/* ---  vystup  --- */

$output .= $message . "
<fieldset>
<legend>" . _lang('admin.other.cleanup.cleanup') . "</legend>
<form class='cform' action='index.php?p=other-cleanup' method='post'>
<input type='hidden' name='action' value='1'>
<p>" . _lang('admin.other.cleanup.cleanup.p') . "</p>

<table>
<tr class='valign-top'>

<td rowspan='2'>
  <fieldset>
  <legend>" . _lang('mod.messages') . "</legend>
  <label><input type='radio' name='messages' value='0'" . Form::activateCheckbox(!isset($_POST['messages']) || Request::post('messages') == 0) . "> " . _lang('global.noaction') . "</label><br>
  <label><input type='radio' name='messages' value='1'" . Form::activateCheckbox(isset($_POST['messages']) && Request::post('messages') == 1) . "> " . _lang('admin.other.cleanup.messages.1') . "</label> " . $selectTime("messages-time") . "<br>
  <label><input type='radio' name='messages' value='2'" . Form::activateCheckbox(isset($_POST['messages']) && Request::post('messages') == 2) . "> " . _lang('admin.other.cleanup.messages.2') . "</label>
  </fieldset>

  <fieldset>
  <legend>" . _lang('admin.users.users') . "</legend>
  <p class='bborder'><label><input type='checkbox' name='users' value='1'" . Form::activateCheckbox(isset($_POST['users'])) . "> " . _lang('admin.other.cleanup.users') . "</label></p>
  <table>

  <tr>
  <th>" . _lang('admin.other.cleanup.users.time') . "</th>
  <td>" . $selectTime("users-time") . "</td>
  </tr>

  <tr>
  <th>" . _lang('admin.other.cleanup.users.group') . "</th>
  <td>" . Admin::userSelect("users-group", (isset($_POST['users-group']) ? (int) Request::post('users-group') : -1), "1", null, _lang('global.all'), true) . "</td>
  </tr>

  </table>
  </fieldset>
</td>

<td>
  <fieldset>
  <legend>" . _lang('global.other') . "</legend>
  <label><input type='checkbox' name='maintenance' value='1' checked> " . _lang('admin.other.cleanup.other.maintenance') . "</label><br>
  <label><input type='checkbox' name='optimize' value='1' checked> " . _lang('admin.other.cleanup.other.optimize') . "</label><br>
  <label><input type='checkbox' name='comments' value='1'" . Form::activateCheckbox(isset($_POST['comments'])) . "> " . _lang('admin.other.cleanup.other.comments') . "</label><br>
  <label><input type='checkbox' name='posts' value='1'" . Form::activateCheckbox(isset($_POST['posts'])) . "> " . _lang('admin.other.cleanup.other.posts') . "</label><br>
  <label><input type='checkbox' name='plugin_posts' value='1'" . Form::activateCheckbox(isset($_POST['plugin_posts'])) . "> " . _lang('admin.other.cleanup.other.plugin_posts') . "</label><br>
  <label><input type='checkbox' name='iplog' value='1'" . Form::activateCheckbox(isset($_POST['iplog'])) . "> " . _lang('admin.other.cleanup.other.iplog') . "</label><br>
  <label><input type='checkbox' name='user_activation' value='1'" . Form::activateCheckbox(isset($_POST['user_activation'])) . "> " . _lang('admin.other.cleanup.other.user_activation') . "</label>
  </fieldset>
</td>

</tr>

<tr class='valign-top'>

<td align='center'><p>
<input type='submit' value='" . _lang('admin.other.cleanup.prev') . "'><br><br>
<input type='submit' name='do_cleanup' value='" . _lang('admin.other.cleanup.do') . "' onclick='return Sunlight.confirm();'>
</p></td>

</tr>

</table>

" . Xsrf::getInput() . "</form>
</fieldset>
<br>

<fieldset>
<legend>" . _lang('admin.other.cleanup.uninstall') . "</legend>
<form class='cform' action='index.php?p=other-cleanup' method='post' autocomplete='off'>
<input type='hidden' name='action' value='2'>
<p class='bborder'>" . _lang('admin.other.cleanup.uninstall.p') . "</p>
" . Admin::note(_lang('admin.other.cleanup.uninstall.note', array('*prefix*' => _dbprefix)), true, 'warn') . "
<p><label><input type='checkbox' name='confirm' value='1'> " . _lang('admin.other.cleanup.uninstall.confirm', array('*dbname*' => _dbname)) . "</label></p>
<p><strong>" . _lang('admin.other.cleanup.uninstall.pass') . ":</strong>  <input type='password' class='inputsmall' name='pass' autocomplete='off'></p>
<input type='submit' value='" . _lang('global.do') . "' onclick='return Sunlight.confirm();'>
" . Xsrf::getInput() . "</form>
</fieldset>
";
