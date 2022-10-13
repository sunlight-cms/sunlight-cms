<?php

use Sunlight\Post\Post;
use Sunlight\Post\PostService;
use Sunlight\Util\Request;
use Sunlight\Database\Database as DB;

defined('SL_ROOT') or exit;

$id = (int) Request::get('id');
[$columns, $joins, $cond] = Post::createFilter('post');
$post = DB::queryRow('SELECT ' . $columns . ' FROM ' . DB::table('post') . ' post ' . $joins . ' WHERE post.id=' . $id . ' AND ' . $cond);

if ($post === false) {
    $_index->notFound();
    return;
}

$_index->redirect(PostService::getCurrentPostUrl($post));
