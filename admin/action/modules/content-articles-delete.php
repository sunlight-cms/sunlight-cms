<?php

use Sunlight\Admin\Admin;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

$continue = false;

if (isset($_GET['id'], $_GET['returnid'], $_GET['returnpage'])) {
    $id = (int) Request::get('id');
    $returnid = (int) Request::get('returnid');
    $returnpage = (int) Request::get('returnpage');
    $query = DB::queryRow('SELECT title FROM ' . DB::table('article') . ' WHERE id=' . $id . ' AND ' . Admin::articleAccessSql());

    if ($query !== false) {
        $continue = true;
    }
}

// process
if (isset($_POST['confirm'])) {
    // delete comments
    DB::delete('post', 'type=' . Post::ARTICLE_COMMENT . ' AND home=' . $id);

    // delete article
    DB::delete('article', 'id=' . $id);

    // extend event
    Extend::call('admin.article.delete', ['id' => $id]);

    // redirect
    $_admin->redirect(Router::admin('content-articles-list', ['query' => ['cat' => $returnid, 'page' => $returnpage, 'artdeleted' => 1]]));

    return;
}

// output
if ($continue) {
    $output .=
Admin::backlink(Router::admin('content-articles-list', ['query' => ['cat' => $returnid, 'page' => $returnpage]])) . '
<h1>' . _lang('admin.content.articles.delete.title') . '</h1>
<p class="bborder">' . _lang('admin.content.articles.delete.p', ['%arttitle%' => $query['title']]) . '</p>
' . Form::start('article_delete', [
    'class' => 'cform',
    'action' => Router::admin('content-articles-delete', ['query' => ['id' => $id, 'returnid' => $returnid, 'returnpage' => $returnpage]]),
]) . '
' . Form::input('hidden', 'confirm', '1'). '
' . Form::input('submit', null, _lang('admin.content.articles.delete.confirmbox')) . '
' . Form::end('article_delete') . '
';
} else {
    $output .= Message::error(_lang('global.badinput'));
}
