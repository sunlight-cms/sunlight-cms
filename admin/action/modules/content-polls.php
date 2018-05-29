<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

/* ---  odstraneni ankety  --- */

$message = "";
if (isset($_GET['del']) && \Sunlight\Xsrf::check(true)) {
    $del = (int) \Sunlight\Util\Request::get('del');
    DB::query("DELETE FROM p USING " . _polls_table . " AS p WHERE p.id=" . $del . \Sunlight\Admin\Admin::pollAccess());
    if (DB::affectedRows() != 0) {
        $message = \Sunlight\Message::render(_msg_ok, _lang('global.done'));
    }
}

/* ---  vystup  --- */

// filtr autoru
if (_priv_adminpollall && isset($_GET['author']) && \Sunlight\Util\Request::get('author') != -1) {
    $pasep = true;
    $author_filter_id = (int) \Sunlight\Util\Request::get('author');
    $author_filter = "p.author=" . (int) \Sunlight\Util\Request::get('author');
} else {
    $pasep = false;
    $author_filter = "";
    $author_filter_id = -1;
}

$output .= "
<p class='bborder'>" . _lang('admin.content.polls.p') . "</p>
<p><a class='button' href='index.php?p=content-polls-edit'><img src='images/icons/new.png' class='icon' alt='new'>" . _lang('admin.content.polls.new') . "</a></p>
";

// filtr
if (_priv_adminpollall) {
    $output .= "
  <form class='cform' action='index.php' method='get'>
  <input type='hidden' name='p' value='content-polls'>
  <strong>" . _lang('admin.content.polls.filter') . ":</strong> " . \Sunlight\Admin\Admin::userSelect("author", $author_filter_id, "adminpoll=1", null, _lang('global.all2')) . " <input class='button' type='submit' value='" . _lang('global.apply') . "'>
  </form>
  ";
}

// strankovani
$paging = \Sunlight\Paginator::render("index.php?p=content-polls", 20, _posts_table . ':p', $author_filter . \Sunlight\Admin\Admin::pollAccess($pasep), "&amp;filter=" . $author_filter_id);
$output .= $paging['paging'];

$output .= $message . "
<table class='list list-hover list-max'>
<thead><tr><td>" . _lang('admin.content.form.question') . "</td>" . (_priv_adminpollall ? "<td>" . _lang('article.author') . "</td>" : '') . "<td>" . _lang('global.id') . "</td><td>" . _lang('global.action') . "</td></tr></thead>
<tbody>
";

// vypis anket
$userQuery = \Sunlight\User::createQuery('p.author');
$query = DB::query("SELECT p.id,p.question,p.locked," . $userQuery['column_list'] . " FROM " . _polls_table . " p " . $userQuery['joins'] . " WHERE " . $author_filter . \Sunlight\Admin\Admin::pollAccess($pasep) . " ORDER BY p.id DESC " . $paging['sql_limit']);
if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {
        if (_priv_adminpollall) {
            $username = "<td>" . \Sunlight\Router::userFromQuery($userQuery, $item) . "</td>";
        } else {
            $username = "";
        }
        $output .= "<tr>"
            . "<td>" . \Sunlight\Util\StringManipulator::ellipsis($item['question'], 64) . (($item['locked'] == 1) ? " (" . _lang('admin.content.form.locked') . ")" : '') . "</td>"
            . $username
            . "<td>" . $item['id'] . "</td>"
            . "<td class='actions'>
                <a class='button' href='index.php?p=content-polls-edit&amp;id=" . $item['id'] . "'><img src='images/icons/edit.png' class='icon' alt='edit'> " . _lang('global.edit') . "</a>
                <a class='button' href='" . \Sunlight\Xsrf::addToUrl("index.php?p=content-polls&amp;author=" . $author_filter_id . "&amp;page=" . $paging['current'] . "&amp;del=" . $item['id']) . "' onclick='return Sunlight.confirm();'><img src='images/icons/delete.png' class='icon' alt='del'> " . _lang('global.delete') . "</a>
            </td>"
            . "</tr>\n";
    }
} else {
    $output .= "<tr><td colspan='" . (_priv_adminpollall ? "4" : "3") . "'>" . _lang('global.nokit') . "</td></tr>";
}

$output .= "
</tbody>
</table>

" . $paging['paging'] . "

<form class='cform' action='index.php' method='get'>
<input type='hidden' name='p' value='content-polls-edit'>
" . _lang('admin.content.polls.openid') . ": <input type='number' name='id' class='inputmini'> <input class='button' type='submit' value='" . _lang('global.open') . "'>
</form>
";
