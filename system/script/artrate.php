<?php

use Sunlight\Article;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\IpLog;
use Sunlight\Router;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

require '../bootstrap.php';
Core::init('../../');

if (_ratemode == 0) {
    exit;
}

/* ---  hodnoceni  --- */

// nacteni promennych
$id = (int) Request::post('id');

$article_exists = false;

// kontrola promennych a pristupu
$continue = false;
$query = DB::queryRow("SELECT art.id,art.slug,art.time,art.confirmed,art.author,art.public,art.home1,art.home2,art.home3,art.rateon,cat.slug AS cat_slug FROM " . _article_table . " AS art  JOIN " . _page_table . " AS cat ON(cat.id=art.home1) WHERE art.id=" . $id);
if ($query !== false) {
    $article_exists = true;
    if (isset($_POST['r'])) {
        $r = round(Request::post('r') / 10) * 10;
        if (IpLog::check(_iplog_article_rated, $id) && Xsrf::check() && $query['rateon'] == 1 && Article::checkAccess($query) && $r <= 100 && $r >= 0) {
            $continue = true;
        }
    }
}

// zapocteni hodnoceni
if ($continue) {
    DB::update(_article_table, 'id=' . $id, [
        'ratenum' => DB::raw('ratenum+1'),
        'ratesum' => DB::raw('ratesum+' . $r)
    ]);
    IpLog::update(_iplog_article_rated, $id);
}

// presmerovani
if ($article_exists) {
    $aurl = Router::article($id, $query['slug'], null, true) . "#article-info";
} else {
    $aurl = Core::$url;
}
header("location: " . $aurl);
