<?php

if (!defined('_root')) {
    exit;
}

/* ---  priprava  --- */

$message = "";
$infopage = false;

function _admin_boolSelect($name, $type2 = false)
{
    return "
<select name='" . $name . "'>
<option value='-1'>" . (($type2 == false) ? _lang('admin.content.artfilter.f1.bool.doesntmatter') : _lang('global.nochange')) . "</option>
<option value='1'>" . _lang('admin.content.artfilter.f1.bool.mustbe') . "</option>
<option value='0'>" . _lang('admin.content.artfilter.f1.bool.mustntbe') . "</option>
</select> \n";
}

/* ---  akce  --- */

if (isset($_POST['category'])) {

    // nacteni promennych
    $category = (int) _post('category');
    $author = (int) _post('author');
    $time = _loadTime('time', time());
    $ba = (int) _post('ba');
    $public = (int) _post('public');
    $visible = (int) _post('visible');
    $confirmed = (int) _post('confirmed');
    $comments = (int) _post('comments');
    $rateon = (int) _post('rateon');
    $showinfo = (int) _post('showinfo');
    $new_category = (int) _post('new_category');
    $new_author = (int) _post('new_author');
    $new_public = (int) _post('new_public');
    $new_visible = (int) _post('new_visible');
    if (_priv_adminconfirm) {
        $new_confirmed = (int) _post('new_confirmed');
    }
    $new_comments = (int) _post('new_comments');
    $new_rateon = (int) _post('new_rateon');
    $new_showinfo = (int) _post('new_showinfo');
    $new_delete = _checkboxLoad("new_delete");
    $new_resetrate = _checkboxLoad("new_resetrate");
    $new_delcomments = _checkboxLoad("new_delcomments");
    $new_resetread = _checkboxLoad("new_resetread");

    // kontrola promennych
    if ($new_category != -1) {
        if (DB::count(_root_table, 'id=' . DB::val($new_category) . ' AND type=' . _page_category) === 0) {
            $new_category = -1;
        }
    }
    if ($new_author != -1) {
        if (DB::count( _users_table, 'id=' . DB::val($new_author)) === 0) {
            $new_author = -1;
        }
    }

    // sestaveni casti sql dotazu - 'where'
    $params = array("category", "author", "time", "public", "visible", "confirmed", "comments", "rateon", "showinfo");
    $cond = "";

    // cyklus
    foreach ($params as $param) {

        $skip = false;
        if ($param == "category" || $param == "author" || $param == "time") {

            switch ($param) {

                case "category":
                    if ($$param != "-1") {
                        $cond .= _articleFilterCategories(array($$param), 'art');
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
    $query = DB::query("SELECT art.id,art.title,art.slug,cat.slug AS cat_slug FROM " . _articles_table . " AS art JOIN " . _root_table . " AS cat ON(cat.id=art.home1) WHERE " . $cond);
    $found = DB::size($query);
    if ($found != 0) {
        if (!_checkboxLoad("_process")) {
            $infopage = true;
        } else {
            $boolparams = array("public", "visible", "comments", "rateon", "showinfo");
            if (_priv_adminconfirm) {
                $boolparams[] = "confirmed";
            }
            while ($item = DB::row($query)) {

                // smazani komentaru
                if ($new_delcomments || $new_delete) {
                    DB::delete(_posts_table, 'type=' . _post_article_comment . ' AND home=' . $item['id']);
                }

                // smazani clanku
                if ($new_delete) {
                    DB::delete(_articles_table, 'id=' . $item['id']);
                    continue;
                }

                // vynulovani hodnoceni
                if ($new_resetrate) {
                    DB::update(_articles_table, 'id=' . $item['id'], array(
                        'ratenum' => 0,
                        'ratesum' => 0
                    ));
                    DB::delete(_iplog_table, 'type=' . _iplog_article_rated . ' AND var=' . $item['id']);
                }

                // vynulovani poctu precteni
                if ($new_resetread) {
                    DB::update(_articles_table, 'id=' . $item['id'], array('readnum' => 0));
                }

                // zmena kategorie
                if ($new_category != -1) {
                    DB::update(_articles_table, 'id=' . $item['id'], array(
                        'home1' => $new_category,
                        'home2' => -1,
                        'home3' => -1
                    ));
                }

                // zmena autora
                if ($new_author != -1) {
                    DB::update(_articles_table, 'id=' . $item['id'], array('author' => $new_author));
                }

                // konfigurace
                $updatedata = array();
                foreach ($boolparams as $param) {
                    $paramvar = "new_" . $param;
                    $paramval = $$paramvar;
                    if ($paramval == 0 || $paramval == 1) {
                        $updatedata[$param] = $paramval;
                    }
                }
                DB::update(_articles_table, 'id=' . $item['id'], $updatedata);

            }
            $message = _msg(_msg_ok, _lang('global.done'));
        }
    } else {
        $message = _msg(_msg_warn, _lang('admin.content.artfilter.f1.noresult'));
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
<td>" . _adminRootSelect("category", array('type' => _page_category, 'empty_item' => _lang('global.any2'))) . "</td>
</tr>

<tr>
<th>" . _lang('article.author') . "</th>
<td>" . _adminUserSelect("author", -1, "adminart=1", "selectmedium", _lang('global.any')) . "</td>
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

" . _editTime('time', -1) . "

</td>
</tr>

<tr class='valign-top'>
<th>" . _lang('admin.content.form.settings') . "</th>
<td>
" . _admin_boolSelect("public") . _lang('admin.content.form.public') . "<br>
" . _admin_boolSelect("visible") . _lang('admin.content.form.visible') . "<br>
" . _admin_boolSelect("confirmed") . _lang('admin.content.form.confirmed') . "<br>
" . _admin_boolSelect("comments") . _lang('admin.content.form.comments') . "<br>
" . _admin_boolSelect("rateon") . _lang('admin.content.form.artrate') . "<br>
" . _admin_boolSelect("showinfo") . _lang('admin.content.form.showinfo') . "
</td>
</tr>

</table>

<br><div class='hr'><hr></div><br>

<h2>" . _lang('admin.content.artfilter.f2.title') . "</h2>
<p>" . _lang('admin.content.artfilter.f2.p') . "</p>
<table>

<tr>
<th>" . _lang('article.category') . "</th>
<td>" . _adminRootSelect("new_category", array('type' => _page_category, 'empty_item' => _lang('global.nochange'))) . "</td>
</tr>

<tr>
<th>" . _lang('article.author') . "</th>
<td>" . _adminUserSelect("new_author", -1, "adminart=1", "selectmedium", _lang('global.nochange')) . "</td>
</tr>

<tr class='valign-top'>
<th>" . _lang('admin.content.form.settings') . "</th>
<td>
" . _admin_boolSelect("new_public", true) . _lang('admin.content.form.public') . "<br>
" . _admin_boolSelect("new_visible", true) . _lang('admin.content.form.visible') . "<br>
" . (_priv_adminconfirm ? _admin_boolSelect("new_confirmed", true) . _lang('admin.content.form.confirmed') . "<br>" : '') . "
" . _admin_boolSelect("new_comments", true) . _lang('admin.content.form.comments') . "<br>
" . _admin_boolSelect("new_rateon", true) . _lang('admin.content.form.artrate') . "<br>
" . _admin_boolSelect("new_showinfo", true) . _lang('admin.content.form.showinfo') . "
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
    $output .= _renderHiddenPostInputs(null, null, array('_process')) . "
<input type='hidden' name='_process' value='1'>
" . _msg(_msg_ok, _lang('admin.content.artfilter.f1.infotext', array("*found*" => $found))) . "
<ul>";

    $counter = 0;
    while ($r = DB::row($query)) {
        if ($counter >= 30) {
            $output .= "<li><em>... (+" . ($found - $counter) . ")</em></li>\n";
            break;
        }
        $output .= "<li><a href='" . _linkArticle($r['id'], $r['slug'], $r['cat_slug']) . "' target='_blank'>" . $r['title'] . "</a></li>\n";
        ++$counter;
    }

    $output .="</ul>
<input type='submit' value='" . _lang('global.do2') . "'> <a href='index.php?p=content-artfilter'>" . _lang('global.cancel') . "</a>
";
}

$output .= _xsrfProtect() . "</form>";
