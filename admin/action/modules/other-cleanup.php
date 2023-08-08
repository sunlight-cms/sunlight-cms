<?php

use Sunlight\Admin\Admin;
use Sunlight\Logger;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\SystemMaintenance;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

$selectTime = function ($name) {
    $opts = [1, 2, 4, 8, 25, 52, 104];
    $active = (isset($_POST[$name]) ? (int) Request::post($name) : 25);
    $output = '<select name="' . $name . "\">\n";

    for ($i = 0; isset($opts[$i]); ++$i) {
        $output .= '<option value="' . $opts[$i] . '"' . Form::selectOption($active === $opts[$i]) . '>' . _lang('admin.other.cleanup.time.' . $opts[$i]) . "</option>\n";
    }

    $output .= "</select>\n";

    return $output;
};

// action
if (isset($_POST['action'])) do {
    // preview?
    $prev = Request::post('action') !== 'do_cleanup';
    $prev_count = [];

    // messages
    $messages = Request::post('messages');

    switch ($messages) {
        case 1:
            $messages_time = time() - (Request::post('messages-time') * 7 * 24 * 60 * 60);

            if ($prev) {
                $prev_count['mod.messages'] = DB::count('pm', 'update_time<' . $messages_time);
            } else {
                DB::query(
                    'DELETE ' . DB::table('pm') . ',post FROM ' . DB::table('pm')
                    . ' LEFT JOIN ' . DB::table('post') . ' AS post ON (post.type=' . Post::PRIVATE_MSG . ' AND post.home=' . DB::table('pm') . '.id)'
                    . ' WHERE update_time<' . $messages_time
                );
                $logged_db_cleanup_info['deleted_messages_before'] = $messages_time;
            }
            break;

        case 2:
            if ($prev) {
                $prev_count['mod.messages'] = DB::count('post', 'type=' . Post::PRIVATE_MSG);
            } else {
                DB::query('TRUNCATE TABLE ' . DB::table('pm'));
                DB::delete('post', 'type=' . Post::PRIVATE_MSG);
                $logged_db_cleanup_info['deleted_all_messages'] = true;
            }
            break;
    }

    // comments, posts, iplog
    if (Form::loadCheckbox('comments')) {
        if ($prev) {
            $prev_count['admin.settings.functions.comments'] = DB::count('post', 'type=' . Post::SECTION_COMMENT . ' OR type=' . Post::ARTICLE_COMMENT);
        } else {
            DB::delete('post', 'type=' . Post::SECTION_COMMENT . ' OR type=' . Post::ARTICLE_COMMENT);
            $logged_db_cleanup_info['deleted_comments'] = true;
        }
    }

    if (Form::loadCheckbox('posts')) {
        if ($prev) {
            $prev_count['global.posts'] = DB::count('post', 'type IN(' . DB::arr([Post::BOOK_ENTRY, Post::SHOUTBOX_ENTRY, Post::FORUM_TOPIC]) . ')');
        } else {
            DB::deleteSet('post', 'type', [
                Post::BOOK_ENTRY,
                Post::SHOUTBOX_ENTRY,
                Post::FORUM_TOPIC
            ]);
            $logged_db_cleanup_info['deleted_posts'] = true;
        }
    }

    if (Form::loadCheckbox('plugin_posts')) {
        if ($prev) {
            $prev_count['admin.other.cleanup.other.plugin_posts.label'] = DB::count('post', 'type=' . Post::PLUGIN);
        } else {
            DB::delete('post', 'type=' . Post::PLUGIN);
            $logged_db_cleanup_info['deleted_plugin_posts'] = true;
        }
    }

    if (Form::loadCheckbox('iplog')) {
        if ($prev) {
            $prev_count['admin.iplog'] = DB::count('iplog');
        } else {
            DB::query('TRUNCATE TABLE ' . DB::table('iplog'));
            $logged_db_cleanup_info['deleted_iplog'] = true;
        }
    }

    if (Form::loadCheckbox('user_activation')) {
        if ($prev) {
            $prev_count['mod.reg.confirm'] = DB::count('user_activation');
        } else {
            DB::query('TRUNCATE TABLE ' . DB::table('user_activation'));
            $logged_db_cleanup_info['deleted_user_activations'] = true;
        }
    }

    // users
    if (Form::loadCheckbox('users')) {
        $users_time = time() - (Request::post('users-time') * 7 * 24 * 60 * 60);
        $users_group = (int) Request::post('users-group');
        $users_group_cond = ' AND group_id!=' . User::ADMIN_GROUP_ID;

        if ($users_group != -1) {
            $users_group_cond .= ' AND group_id=' . $users_group;
        }

        if ($prev) {
            $prev_count['admin.users.users'] = DB::count('user', 'activitytime<' . $users_time . $users_group_cond);
        } else {
            $userids = DB::query('SELECT id FROM ' . DB::table('user') . ' WHERE activitytime<' . $users_time . $users_group_cond);

            while ($userid = DB::row($userids)) {
                User::delete($userid['id']);
            }
            $logged_db_cleanup_info['deleted_users'] = ['inactive_since' => $users_time, 'group_id' => $users_group];

            unset($userids);
        }
    }

    // maintenance
    if (Form::loadCheckbox('maintenance') && !$prev) {
        SystemMaintenance::run();
    }

    // optimization
    if (Form::loadCheckbox('optimize') && !$prev) {
        foreach (DB::getTablesByPrefix() as $table) {
            DB::query('OPTIMIZE TABLE `' . $table . '`');
        }
        $logged_db_cleanup_info['optimized_tables'] = true;
    }

    // message
    if ($prev) {
        if (empty($prev_count)) {
            $message = Message::warning(_lang('global.noaction'));
            break;
        }

        $message = "<ul>\n";

        foreach ($prev_count as $key => $count) {
            $message .= '<li><strong>' . _lang($key) . ':</strong> <code>' . _num($count) . "</code></li>\n";
        }

        $message .= '</ul>';

        $message = Message::ok(_lang('admin.other.cleanup.found_items') . ':' . $message, true);
    } else {
        if (!empty($logged_db_cleanup_info)) {
            Logger::notice('system', 'Manually performed a database cleanup', $logged_db_cleanup_info);
        }

        $message = Message::ok(_lang('global.done'));
    }
} while (false);

// output
$output .= $message . '
<form class="cform" action="' . _e(Router::admin('other-cleanup')) . '" method="post">
<p>' . _lang('admin.other.cleanup.cleanup.p') . '</p>

<fieldset>
    <legend>' . _lang('mod.messages') . '</legend>
    <label><input type="radio" name="messages" value="0"' . Form::activateCheckbox(!isset($_POST['messages']) || Request::post('messages') == 0) . '> ' . _lang('global.noaction') . '</label><br>
    <label><input type="radio" name="messages" value="1"' . Form::activateCheckbox(isset($_POST['messages']) && Request::post('messages') == 1) . '> ' . _lang('admin.other.cleanup.messages.1') . '</label> ' . $selectTime('messages-time') . '<br>
    <label><input type="radio" name="messages" value="2"' . Form::activateCheckbox(isset($_POST['messages']) && Request::post('messages') == 2) . '> ' . _lang('admin.other.cleanup.messages.2') . '</label>
</fieldset>

<fieldset>
    <legend>' . _lang('admin.users.users') . '</legend>
    <p class="bborder"><label><input type="checkbox" name="users" value="1"' . Form::activateCheckbox(isset($_POST['users'])) . '> ' . _lang('admin.other.cleanup.users') . '</label></p>

    <table>
    
    <tr>
    <th>' . _lang('admin.other.cleanup.users.time') . '</th>
    <td>' . $selectTime('users-time') . '</td>
    </tr>
    
    <tr>
    <th>' . _lang('admin.other.cleanup.users.group') . '</th>
    <td>' . Admin::userSelect('users-group', ['selected' => Request::post('users-group', -1), 'group_cond' => 'id!=' . User::ADMIN_GROUP_ID, 'extra_option' => _lang('global.all'), 'select_groups' => true]) . '</td>
    </tr>
    
    </table>
</fieldset>

<fieldset>
    <legend>' . _lang('global.other') . '</legend>
    <label><input type="checkbox" name="maintenance" value="1" checked> ' . _lang('admin.other.cleanup.other.maintenance') . '</label><br>
    <label><input type="checkbox" name="optimize" value="1" checked> ' . _lang('admin.other.cleanup.other.optimize') . '</label><br>
    <label><input type="checkbox" name="comments" value="1"' . Form::activateCheckbox(isset($_POST['comments'])) . '> ' . _lang('admin.other.cleanup.other.comments') . '</label><br>
    <label><input type="checkbox" name="posts" value="1"' . Form::activateCheckbox(isset($_POST['posts'])) . '> ' . _lang('admin.other.cleanup.other.posts') . '</label><br>
    <label><input type="checkbox" name="plugin_posts" value="1"' . Form::activateCheckbox(isset($_POST['plugin_posts'])) . '> ' . _lang('admin.other.cleanup.other.plugin_posts') . '</label><br>
    <label><input type="checkbox" name="iplog" value="1"' . Form::activateCheckbox(isset($_POST['iplog'])) . '> ' . _lang('admin.other.cleanup.other.iplog') . '</label><br>
    <label><input type="checkbox" name="user_activation" value="1"' . Form::activateCheckbox(isset($_POST['user_activation'])) . '> ' . _lang('admin.other.cleanup.other.user_activation') . '</label>
</fieldset>

<button class="button bigger" name="action" type="submit" value="preview">' . _lang('admin.other.cleanup.prev') . '</button>
<button class="button bigger" name="action" type="submit" value="do_cleanup" onclick="return Sunlight.confirm();">' . _lang('admin.other.cleanup.do') . '</button>

' . Xsrf::getInput() . '</form>
';
