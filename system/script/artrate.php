<?php

require '../bootstrap.php';
Sunlight\Core::init('../../');

if (_ratemode == 0) {
    exit;
}

/* ---  hodnoceni  --- */

// nacteni promennych
$id = (int) _post('id');

$article_exists = false;

// kontrola promennych a pristupu
$continue = false;
$query = DB::query("SELECT art.id,art.slug,art.time,art.confirmed,art.author,art.public,art.home1,art.home2,art.home3,art.rateon,cat.slug AS cat_slug FROM " . _articles_table . " AS art  JOIN " . _root_table . " AS cat ON(cat.id=art.home1) WHERE art.id=" . $id);
if (DB::size($query) != 0) {
    $article_exists = true;
    $query = DB::row($query);
    if (isset($_POST['r'])) {
        $r = round(_post('r') / 10) * 10;
        if (_iplogCheck(_iplog_article_rated, $id) && _xsrfCheck() && $query['rateon'] == 1 && _articleAccess($query) && $r <= 100 && $r >= 0) {
            $continue = true;
        }
    }
}

// zapocteni hodnoceni
if ($continue) {
    DB::query("UPDATE " . _articles_table . " SET ratenum=ratenum+1,ratesum=ratesum+" . $r . " WHERE id=" . $id);
    _iplogUpdate(_iplog_article_rated, $id);
}

// presmerovani
if ($article_exists) {
    $aurl = _linkArticle($id, $query['slug'], null, true) . "#article-info";
} else {
    $aurl = _url;
}
header("location: " . $aurl);
