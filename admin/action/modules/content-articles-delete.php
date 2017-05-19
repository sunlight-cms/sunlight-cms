<?php

if (!defined('_root')) {
    exit;
}

/* ---  nacteni promennych  --- */

$continue = false;
if (isset($_GET['id']) && isset($_GET['returnid']) && isset($_GET['returnpage'])) {
    $id = (int) _get('id');
    $returnid = (int) _get('returnid');
    $returnpage = (int) _get('returnpage');
    $query = DB::queryRow("SELECT title FROM " . _articles_table . " WHERE id=" . $id . _adminArticleAccess());
    if ($query !== false) {
        $continue = true;
    }
}

/* ---  ulozeni  --- */

if (isset($_POST['confirm'])) {

    // smazani komentaru
    DB::delete(_posts_table, 'type=' . _post_article_comment . ' AND home=' . $id);

    // smazani clanku
    DB::delete(_articles_table, 'id=' . $id);

    // udalost
    Sunlight\Extend::call('admin.article.delete', array('id' => $id));

    // presmerovani
    $admin_redirect_to = 'index.php?p=content-articles-list&cat=' . $returnid . '&page=' . $returnpage . '&artdeleted';

    return;

}

/* ---  vystup  --- */

if ($continue) {

    $output .=
_adminBacklink('index.php?p=content-articles-list&cat=' . $returnid . '&page=' . $returnpage) . "
<h1>" . $_lang['admin.content.articles.delete.title'] . "</h1>
<p class='bborder'>" . str_replace("*arttitle*", $query['title'], $_lang['admin.content.articles.delete.p']) . "</p>
<form class='cform' action='index.php?p=content-articles-delete&amp;id=" . $id . "&amp;returnid=" . $returnid . "&amp;returnpage=" . $returnpage . "' method='post'>
<input type='hidden' name='confirm' value='1'>
<input type='submit' value='" . $_lang['admin.content.articles.delete.confirmbox'] . "'>
" . _xsrfProtect() . "</form>
";

} else {
    $output .= _msg(_msg_err, $_lang['global.badinput']);
}
