<?php

use Sunlight\Article;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\IpLog;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

require __DIR__ . '/../bootstrap.php';
Core::init();

if (Settings::get('ratemode') == 0) {
    exit;
}

// load variables
$id = (int) Request::post('id');

$article_exists = false;

// check variables and access
$continue = false;
$query = DB::queryRow(
    'SELECT art.id,art.slug,art.time,art.confirmed,art.author,art.public,art.home1,art.home2,art.home3,art.rateon,cat.slug AS cat_slug'
    . ' FROM ' . DB::table('article') . ' AS art'
    . ' JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1)'
    . ' WHERE art.id=' . $id
);

if ($query !== false) {
    $article_exists = true;

    if (isset($_POST['r'])) {
        $r = round(Request::post('r') / 10) * 10;

        if (IpLog::check(IpLog::ARTICLE_RATED, $id) && Xsrf::check() && $query['rateon'] == 1 && Article::checkAccess($query) && $r <= 100 && $r >= 0) {
            $continue = true;
        }
    }
}

// add rating
if ($continue) {
    DB::update('article', 'id=' . $id, [
        'ratenum' => DB::raw('ratenum+1'),
        'ratesum' => DB::raw('ratesum+' . $r)
    ]);
    IpLog::update(IpLog::ARTICLE_RATED, $id);
}

// redirect back
if ($article_exists) {
    $aurl = Router::article($id, $query['slug'], $query['cat_slug'], ['absolute' => true, 'fragment' => 'article-info']);
} else {
    $aurl = Core::getBaseUrl()->build();
}

header('location: ' . $aurl);
