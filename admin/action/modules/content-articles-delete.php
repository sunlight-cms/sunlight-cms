<?php

use Sunlight\Admin\Admin;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

/* ---  nacteni promennych  --- */

$continue = false;
if (isset($_GET['id'], $_GET['returnid'], $_GET['returnpage'])) {
    $id = (int) Request::get('id');
    $returnid = (int) Request::get('returnid');
    $returnpage = (int) Request::get('returnpage');
    $query = DB::queryRow('SELECT title FROM ' . DB::table('article') . ' WHERE id=' . $id . Admin::articleAccess());
    if ($query !== false) {
        $continue = true;
    }
}

/* ---  ulozeni  --- */

if (isset($_POST['confirm'])) {

    // smazani komentaru
    DB::delete('post', 'type=' . Post::ARTICLE_COMMENT . ' AND home=' . $id);

    // smazani clanku
    DB::delete('article', 'id=' . $id);

    // udalost
    Extend::call('admin.article.delete', ['id' => $id]);

    // presmerovani
    $_admin->redirect(Router::admin('content-articles-list', ['query' => ['cat' => $returnid, 'page' => $returnpage, 'artdeleted' => 1]]));

    return;

}

/* ---  vystup  --- */

if ($continue) {

    $output .=
Admin::backlink(Router::admin('content-articles-list', ['query' => ['cat' => $returnid, 'page' => $returnpage]])) . '
<h1>' . _lang('admin.content.articles.delete.title') . '</h1>
<p class="bborder">' . _lang('admin.content.articles.delete.p', ['%arttitle%' => $query['title']]) . '</p>
<form class="cform" action="' . _e(Router::admin('content-articles-delete', ['query' => ['id' => $id, 'returnid' => $returnid, 'returnpage' => $returnpage]])) . '" method="post">
<input type="hidden" name="confirm" value="1">
<input type="submit" value="' . _lang('admin.content.articles.delete.confirmbox') . '">
' . Xsrf::getInput() . '</form>
';

} else {
    $output .= Message::error(_lang('global.badinput'));
}
