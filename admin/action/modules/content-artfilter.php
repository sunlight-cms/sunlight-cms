<?php

use Sunlight\Admin\Admin;
use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava  --- */

$message = "";
$infopage = false;

$boolSelect = function ($name, $type2 = false)
{
    return "
<select name='" . $name . "'>
<option value='-1'>" . (($type2 == false) ? _lang('admin.content.artfilter.f1.bool.doesntmatter') : _lang('global.nochange')) . "</option>
<option value='1'>" . _lang('admin.content.artfilter.f1.bool.mustbe') . "</option>
<option value='0'>" . _lang('admin.content.artfilter.f1.bool.mustntbe') . "</option>
</select> \n";
};

/* ---  akce  --- */

if (isset($_POST['category'])) {

    // nacteni promennych
    $category = (int) Request::post('category');
    $author = (int) Request::post('author');
    $time = Form::loadTime('time', time());
    $ba = (int) Request::post('ba');
    $public = (int) Request::post('public');
    $visible = (int) Request::post('visible');
    $confirmed = (int) Request::post('confirmed');
    $comments = (int) Request::post('comments');
    $rateon = (int) Request::post('rateon');
    $showinfo = (int) Request::post('showinfo');
    $new_category = (int) Request::post('new_category');
    $new_author = (int) Request::post('new_author');
    $new_public = (int) Request::post('new_public');
    $new_visible = (int) Request::post('new_visible');
    if (_priv_adminconfirm) {
        $new_confirmed = (int) Request::post('new_confirmed');
    }
    $new_comments = (int) Request::post('new_comments');
    $new_rateon = (int) Request::post('new_rateon');
    $new_showinfo = (int) Request::post('new_showinfo');
    $new_delete = Form::loadCheckbox("new_delete");
    $new_resetrate = Form::loadCheckbox("new_resetrate");
    $new_delcomments = Form::loadCheckbox("new_delcomments");
    $new_resetread = Form::loadCheckbox("new_resetread");

    // kontrola promennych
    if ($new_category != -1) {
        if (DB::count(_page_table, 'id=' . DB::val($new_category) . ' AND type=' . _page_category) === 0) {
            $new_category = -1;
        }
    }
    if ($new_author != -1) {
        if (DB::count( _user_table, 'id=' . DB::val($new_author)) === 0) {
            $new_author = -1;
        }
    }

    // sestaveni casti sql dotazu - 'where'
    $params = ["category", "author", "time", "public", "visible", "confirmed", "comments", "rateon", "showinfo"];
    $cond = "";

    // cyklus
    foreach ($params as $param) {

        $skip = false;
        if ($param == "category" || $param == "author" || $param == "time") {

            switch ($param) {

                case "category":
                    if ($$param != "-1") {
                        $cond .= Article::createCategoryFilter([$$param], 'art');
                    } else {
                        $skip = true;
                    }
                    break;

                case "author":
                    if ($$param != "-1") {
                        $cond .= 'art.' . $param . "=" . $$param;
                    } else {
                        $skip = true;
                    }
                    break;

                case "time":
                    switch ($ba) {
                        case 1:
                            $operator = ">";
                            break;
                        case 2:
                            $operator = "=";
                            break;
                        case 3:
                            $operator = "<";
                            break;
                        default:
                            $skip = true;
                            break;
                    }
                    if (!$skip) {
                        $cond .= 'art.' . $param . $operator . $$param;
                    }
                    break;

            }

        } else {
            // boolean
            switch ($$param) {
                case "1":
                    $cond .= 'art.' . $param . "=1";
                    break;
                case "0":
                    $cond .= 'art.' . $param . "=0";
                    break;
                default:
                    $skip = true;
                    break;
            }
        }

        if (!$skip) {
            $cond .= " AND ";
        }

    }

    // vycisteni podminky
    if ($cond == "") {
        $cond = 1;
    } else {
        $cond = mb_substr($cond, 0, mb_strlen($cond) - 5);
    }

    // vyhledani clanku
    $query = DB::query("SELECT art.id,art.title,art.slug,cat.slug AS cat_slug FROM " . _article_table . " AS art JOIN " . _page_table . " AS cat ON(cat.id=art.home1) WHERE " . $cond);
    $found = DB::size($query);
    if ($found != 0) {
        if (!Form::loadCheckbox("_process")) {
            $infopage = true;
        } else {
            $boolparams = ["public", "visible", "comments", "rateon", "showinfo"];
            if (_priv_adminconfirm) {
                $boolparams[] = "confirmed";
            }
            while ($item = DB::row($query)) {

                // smazani komentaru
                if ($new_delcomments || $new_delete) {
                    DB::delete(_comment_table, 'type=' . _post_article_comment . ' AND home=' . $item['id']);
                }

                // smazani clanku
                if ($new_delete) {
                    DB::delete(_article_table, 'id=' . $item['id']);
                    continue;
                }

                // vynulovani hodnoceni
                if ($new_resetrate) {
                    DB::update(_article_table, 'id=' . $item['id'], [
                        'ratenum' => 0,
                        'ratesum' => 0
                    ]);
                    DB::delete(_iplog_table, 'type=' . _iplog_article_rated . ' AND var=' . $item['id']);
                }

                // vynulovani poctu precteni
                if ($new_resetread) {
                    DB::update(_article_table, 'id=' . $item['id'], ['readnum' => 0]);
                }

                // zmena kategorie
                if ($new_category != -1) {
                    DB::update(_article_table, 'id=' . $item['id'], [
                        'home1' => $new_category,
                        'home2' => -1,
                        'home3' => -1
                    ]);
                }

                // zmena autora
                if ($new_author != -1) {
                    DB::update(_article_table, 'id=' . $item['id'], ['author' => $new_author]);
                }

                // konfigurace
                $updatedata = [];
                foreach ($boolparams as $param) {
                    $paramvar = "new_" . $param;
                    $paramval = $$paramvar;
                    if ($paramval == 0 || $paramval == 1) {
                        $updatedata[$param] = $paramval;
                    }
                }
                DB::update(_article_table, 'id=' . $item['id'], $updatedata);

            }
            $message = Message::ok(_lang('global.done'));
        }
    } else {
        $message = Message::warning(_lang('admin.content.artfilter.f1.noresult'));
    }

}

/* ---  vystup  --- */

$output .= $message . "
<form action='index.php?p=content-artfilter' method='post'>
";

if (!$infopage) {
    $output .= "
<h2>" . _lang('admin.content.artfilter.f1.title') . "</h2>
<p>" . _lang('admin.content.artfilter.f1.p') . "</p>
<table>

<tr>
<th>" . _lang('article.category') . "</th>
<td>" . Admin::pageSelect("category", ['type' => _page_category, 'empty_item' => _lang('global.any2')]) . "</td>
</tr>

<tr>
<th>" . _lang('article.author') . "</th>
<td>" . Admin::userSelect("author", -1, "adminart=1", "selectmedium", _lang('global.any')) . "</td>
</tr>

<tr>
<th>" . _lang('article.posted') . "</th>
<td>

<select name='ba'>
<option value='0'>" . _lang('admin.content.artfilter.f1.time0') . "</option>
<option value='1'>" . _lang('admin.content.artfilter.f1.time1') . "</option>
<option value='2'>" . _lang('admin.content.artfilter.f1.time2') . "</option>
<option value='3'>" . _lang('admin.content.artfilter.f1.time3') . "</option>
</select>

" . Form::editTime('time', -1) . "

</td>
</tr>

<tr class='valign-top'>
<th>" . _lang('admin.content.form.settings') . "</th>
<td>
" . $boolSelect("public") . _lang('admin.content.form.public') . "<br>
" . $boolSelect("visible") . _lang('admin.content.form.visible') . "<br>
" . $boolSelect("confirmed") . _lang('admin.content.form.confirmed') . "<br>
" . $boolSelect("comments") . _lang('admin.content.form.comments') . "<br>
" . $boolSelect("rateon") . _lang('admin.content.form.artrate') . "<br>
" . $boolSelect("showinfo") . _lang('admin.content.form.showinfo') . "
</td>
</tr>

</table>

<br><div class='hr'><hr></div><br>

<h2>" . _lang('admin.content.artfilter.f2.title') . "</h2>
<p>" . _lang('admin.content.artfilter.f2.p') . "</p>
<table>

<tr>
<th>" . _lang('article.category') . "</th>
<td>" . Admin::pageSelect("new_category", ['type' => _page_category, 'empty_item' => _lang('global.nochange')]) . "</td>
</tr>

<tr>
<th>" . _lang('article.author') . "</th>
<td>" . Admin::userSelect("new_author", -1, "adminart=1", "selectmedium", _lang('global.nochange')) . "</td>
</tr>

<tr class='valign-top'>
<th>" . _lang('admin.content.form.settings') . "</th>
<td>
" . $boolSelect("new_public", true) . _lang('admin.content.form.public') . "<br>
" . $boolSelect("new_visible", true) . _lang('admin.content.form.visible') . "<br>
" . (_priv_adminconfirm ? $boolSelect("new_confirmed", true) . _lang('admin.content.form.confirmed') . "<br>" : '') . "
" . $boolSelect("new_comments", true) . _lang('admin.content.form.comments') . "<br>
" . $boolSelect("new_rateon", true) . _lang('admin.content.form.artrate') . "<br>
" . $boolSelect("new_showinfo", true) . _lang('admin.content.form.showinfo') . "
</td>
</tr>

<tr class='valign-top'>
<th>" . _lang('global.action') . "</th>
<td>
<label><input type='checkbox' name='new_delete' value='1'> " . _lang('global.delete') . "</label><br>
<label><input type='checkbox' name='new_resetrate' value='1'> " . _lang('admin.content.form.resetartrate') . "</label><br>
<label><input type='checkbox' name='new_delcomments' value='1'> " . _lang('admin.content.form.delcomments') . "</label><br>
<label><input type='checkbox' name='new_resetread' value='1'> " . _lang('admin.content.form.resetartread') . "</label>
</td>
</tr>

</table>

<br><div class='hr'><hr></div><br>

<input type='submit' value='" . _lang('mod.search.submit') . "'>
";
} else {
    $output .= Form::renderHiddenPostInputs(null, null, ['_process']) . "
<input type='hidden' name='_process' value='1'>
" . Message::ok(_lang('admin.content.artfilter.f1.infotext', ["%found%" => $found])) . "
<ul>";

    $counter = 0;
    while ($r = DB::row($query)) {
        if ($counter >= 30) {
            $output .= "<li><em>... (+" . ($found - $counter) . ")</em></li>\n";
            break;
        }
        $output .= "<li><a href='" . Router::article($r['id'], $r['slug'], $r['cat_slug']) . "' target='_blank'>" . $r['title'] . "</a></li>\n";
        ++$counter;
    }

    $output .="</ul>
<input type='submit' value='" . _lang('global.do2') . "'> <a href='index.php?p=content-artfilter'>" . _lang('global.cancel') . "</a>
";
}

$output .= Xsrf::getInput() . "</form>";
