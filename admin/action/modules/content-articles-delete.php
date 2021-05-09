<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  nacteni promennych  --- */

$continue = false;
if (isset($_GET['id'], $_GET['returnid'], $_GET['returnpage'])) {
    $id = (int) Request::get('id');
    $returnid = (int) Request::get('returnid');
    $returnpage = (int) Request::get('returnpage');
    $query = DB::queryRow("SELECT title FROM " . _article_table . " WHERE id=" . $id . Admin::articleAccess());
    if ($query !== false) {
        $continue = true;
    }
}

/* ---  ulozeni  --- */

if (isset($_POST['confirm'])) {

    // smazani komentaru
    DB::delete(_comment_table, 'type=' . _post_article_comment . ' AND home=' . $id);

    // smazani clanku
    DB::delete(_article_table, 'id=' . $id);

    // udalost
    Extend::call('admin.article.delete', ['id' => $id]);

    // presmerovani
    $admin_redirect_to = 'index.php?p=content-articles-list&cat=' . $returnid . '&page=' . $returnpage . '&artdeleted';

    return;

}

/* ---  vystup  --- */

if ($continue) {

    $output .=
Admin::backlink('index.php?p=content-articles-list&cat=' . $returnid . '&page=' . $returnpage) . "
<h1>" . _lang('admin.content.articles.delete.title') . "</h1>
<p class='bborder'>" . _lang('admin.content.articles.delete.p', ["%arttitle%" => $query['title']]) . "</p>
<form class='cform' action='index.php?p=content-articles-delete&amp;id=" . $id . "&amp;returnid=" . $returnid . "&amp;returnpage=" . $returnpage . "' method='post'>
<input type='hidden' name='confirm' value='1'>
<input type='submit' value='" . _lang('admin.content.articles.delete.confirmbox') . "'>
" . Xsrf::getInput() . "</form>
";

} else {
    $output .= Message::error(_lang('global.badinput'));
}
