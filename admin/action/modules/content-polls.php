<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

// remove a poll
if (isset($_GET['del']) && Xsrf::check(true)) {
    $del = (int) Request::get('del');
    DB::query('DELETE FROM p USING ' . DB::table('poll') . ' AS p WHERE p.id=' . $del . Admin::pollAccess());

    if (DB::affectedRows() != 0) {
        $message = Message::ok(_lang('global.done'));
    }
}

// output
if (User::hasPrivilege('adminpollall') && isset($_GET['author']) && Request::get('author') != -1) {
    $author_filter_id = (int) Request::get('author');
    $author_filter = 'p.author=' . (int) Request::get('author');
} else {
    $author_filter = '';
    $author_filter_id = -1;
}

$output .= '
<p class="bborder">' . _lang('admin.content.polls.p') . '</p>
<p><a class="button" href="' . _e(Router::admin('content-polls-edit')) . '"><img src="' . _e(Router::path('admin/public/images/icons/new.png')) . '" class="icon" alt="new">' . _lang('admin.content.polls.new') . '</a></p>
';

// filter
if (User::hasPrivilege('adminpollall')) {
    $output .= '
  ' . Form::start('polls-filter', ['class' => 'cform', 'action' => Router::admin(null), 'method' => 'get']) . '
  ' . Form::input('hidden', 'p', 'content-polls') . '
  <strong>' . _lang('admin.content.polls.filter') . ':</strong> '
    . Admin::userSelect('author', ['selected' => $author_filter_id, 'group_cond' => 'adminpoll=1', 'extra_option' => _lang('global.all2')])
    . ' ' . Form::input('submit', null, _lang('global.apply'), ['class' => 'button']) . ' 
  ' . Form::end('polls-filter') . '
  ';
}

// paging
$paging = Paginator::paginateTable(
    Router::admin('content-polls'),
    20,
    DB::table('poll'),
    [
        'alias' => 'p',
        'cond' => $author_filter . Admin::pollAccess($author_filter !== ''),
        'link_suffix' => '&filter=' . $author_filter_id,
    ]
);
$output .= $paging['paging'];

$output .= $message . '
<div class="horizontal-scroller">
<table class="list list-hover list-max">
<thead><tr><td>' . _lang('admin.content.form.question') . '</td>' . (User::hasPrivilege('adminpollall') ? '<td>' . _lang('article.author') . '</td>' : '') . '<td>' . _lang('global.id') . '</td><td>' . _lang('global.action') . '</td></tr></thead>
<tbody>
';

// list polls
$userQuery = User::createQuery('p.author');
$query = DB::query('SELECT p.id,p.question,p.locked,' . $userQuery['column_list'] . ' FROM ' . DB::table('poll') . ' p ' . $userQuery['joins'] . ' WHERE ' . $author_filter . Admin::pollAccess($author_filter !== '') . ' ORDER BY p.id DESC ' . $paging['sql_limit']);

if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {
        if (User::hasPrivilege('adminpollall')) {
            $username = '<td>' . Router::userFromQuery($userQuery, $item) . '</td>';
        } else {
            $username = '';
        }

        $output .= '<tr>'
            . '<td class="no-wrap">' . StringHelper::ellipsis($item['question'], 64) . (($item['locked'] == 1) ? ' (' . _lang('admin.content.form.locked') . ')' : '') . '</td>'
            . $username
            . '<td>' . $item['id'] . '</td>'
            . '<td class="actions">
                <a class="button" href="' . _e(Router::admin('content-polls-edit', ['query' => ['id' => $item['id']]])) . '">
                    <img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" class="icon" alt="edit"> ' . _lang('global.edit') . '
                </a>
                <a class="button" href="' . _e(Xsrf::addToUrl(Router::admin('content-polls', ['query' => ['author' => $author_filter_id, 'page' => $paging['current'], 'del' => $item['id']]]))) . '" onclick="return Sunlight.confirm();">
                    <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" class="icon" alt="del"> ' . _lang('global.delete') . '
                </a>
            </td>'
            . "</tr>\n";
    }
} else {
    $output .= '<tr><td colspan="' . (User::hasPrivilege('adminpollall') ? '4' : '3') . '">' . _lang('global.nokit') . '</td></tr>';
}

$output .= '
</tbody>
</table>
</div>

' . $paging['paging'] . '

' . Form::start('poll-open-id', ['class' => 'cform', 'action' => Router::admin(null), 'method' => 'get']) . '
' . Form::input('hidden', 'p', 'content-polls-edit') . '
' . _lang('admin.content.polls.openid') . ': ' . Form::input('number', 'id', null, ['class' => 'inputmini']) . ' ' . Form::input('submit', null, _lang('global.open'), ['class' => 'button']) . '
' . Form::end('poll-open-id') . "\n";
