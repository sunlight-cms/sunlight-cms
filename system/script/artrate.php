<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;

require '../bootstrap.php';
Core::init('../../');

if (_ratemode == 0) {
    exit;
}

/* ---  hodnoceni  --- */

// nacteni promennych
$id = (int) _post('id');

$article_exists = false;

// kontrola promennych a pristupu
$continue = false;
$query = DB::queryRow("SELECT art.id,art.slug,art.time,art.confirmed,art.author,art.public,art.home1,art.home2,art.home3,art.rateon,cat.slug AS cat_slug FROM " . _articles_table . " AS art  JOIN " . _root_table . " AS cat ON(cat.id=art.home1) WHERE art.id=" . $id);
if ($query !== false) {
    $article_exists = true;
    if (isset($_POST['r'])) {
        $r = round(_post('r') / 10) * 10;
        if (_iplogCheck(_iplog_article_rated, $id) && _xsrfCheck() && $query['rateon'] == 1 && _articleAccess($query) && $r <= 100 && $r >= 0) {
            $continue = true;
        }
    }
}

// zapocteni hodnoceni
if ($continue) {
    DB::update(_articles_table, 'id=' . $id, array(
        'ratenum' => DB::raw('ratenum+1'),
        'ratesum' => DB::raw('ratesum+' . $r)
    ));
    _iplogUpdate(_iplog_article_rated, $id);
}

// presmerovani
if ($article_exists) {
    $aurl = _linkArticle($id, $query['slug'], null, true) . "#article-info";
} else {
    $aurl = _url;
}
header("location: " . $aurl);
