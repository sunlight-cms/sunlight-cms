<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;

if (!defined('_root')) {
    exit;
}

if (!_logged_in && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

if (!_search) {
    $_index['is_found'] = false;
    return;
}

/* ---  priprava  --- */

if (isset($_GET['q']) && _xsrfCheck(true)) {
    $search_query = trim(_get('q'));
    $root = isset($_GET['root']);
    $art = isset($_GET['art']);
    $post = isset($_GET['post']);
    $image = isset($_GET['img']);
} else {
    $search_query = '';
    $root = true;
    $art = true;
    $post = true;
    $image = true;
}

/* ---  modul  --- */

$_index['title'] = _lang('mod.search');

$output .= "
<p class='bborder'>" . _lang('mod.search.p') . "</p>

<form action='" . _linkModule('search') . "' method='get'>
" . (!_pretty_urls ? _renderHiddenInputs(_arrayFilter($_GET, null, null, array('q', 'root', 'art', 'post', 'img'))) : '') . "
<p><input type='search' name='q' class='inputmedium' value='" . _e($search_query) . "'> <input type='submit' value='" . _lang('mod.search.submit') . "'></p>
<p>
    " . _lang('mod.search.where') . ":
    <label><input type='checkbox' name='root' value='1'" . _checkboxActivate($root) . "> " . _lang('mod.search.where.root') . "</label>
    <label><input type='checkbox' name='art' value='1'" . _checkboxActivate($art) . "> " . _lang('mod.search.where.articles') . "</label>
    <label><input type='checkbox' name='post' value='1'" . _checkboxActivate($post) . "> " . _lang('mod.search.where.posts') . "</label>
    <label><input type='checkbox' name='img' value='1'" . _checkboxActivate($image) . "> " . _lang('mod.search.where.images') . "</label>
</p>

" . _xsrfProtect() . "
</form>

";

/* ---  vyhledavani --- */

if ($search_query != '') {
    if (mb_strlen($search_query) >= 3) {
        // priprava
        $search_query_sql = DB::esc('%' . $search_query . '%');
        $results = array(); // polozka: array(link, titulek, perex)
        $public = !_logged_in;

        // funkce na skladani vyhledavaciho dotazu
        $searchQuery = function ($alias, $cols) {
            if ($alias === null) {
                $alias = '';
            } else {
                $alias = $alias . '.';
            }
            $output = '(';
            for ($i = 0, $last = (sizeof($cols) - 1); isset($cols[$i]); ++$i) {
                $output .= $alias . $cols[$i] . ' LIKE \'' . $GLOBALS['search_query_sql'] . '\'';
                if ($i !== $last) {
                    $output .= ' OR ';
                }
            }
            $output .= ')';

            return $output;
        };

        // vyhledani stranek
        if ($root) {
            $q = DB::query('SELECT id,title,slug,perex FROM ' . _root_table . ' WHERE level<=' . _priv_level . ' AND ' . ($public ? 'public=1 AND ' : '') . $searchQuery(null, array('title', 'slug', 'description', 'perex', 'content')) . ' LIMIT 50');
            while($r = DB::row($q)) {
                $results[] = array(
                    _linkRoot($r['id'], $r['slug']),
                    $r['title'],
                    strip_tags($r['perex'])
                );
            }
            DB::free($q);
        }

        // vyhledani clanku
        if ($art) {
            // zakladni dostaz
            list($joins, $cond) = _articleFilter('art', array(), $searchQuery('art', array('title', 'slug', 'perex', 'description', 'content')));

            // vykonani a nacteni vysledku
            $q = DB::query('SELECT art.id,art.title,art.slug,art.perex,cat1.slug AS cat_slug FROM ' . _articles_table . ' art ' . $joins . ' WHERE ' . $cond . 'ORDER BY time DESC LIMIT 100');
            while($r = DB::row($q)) {
                $results[] = array(
                    _linkArticle($r['id'], $r['slug'], $r['cat_slug']),
                    $r['title'],
                    _cutText(strip_tags($r['perex']), 255, false)
                );
            }
            DB::free($q);
        }

        // vyhledani prispevku
        if ($post) {
            // priprava
            $types = array(_post_section_comment, _post_article_comment, _post_book_entry, _post_forum_topic, _post_plugin);
            list($columns, $joins, $cond) = _postFilter('post', $types, array(), $searchQuery('post', array('subject', 'text')));
            $userQuery = _userQuery('post.author');
            $columns .= ',' . $userQuery['column_list'];
            $joins .= ' ' . $userQuery['joins'];

            // vykonani dotazu
            $q = DB::query($x = 'SELECT ' . $columns . ' FROM ' . _posts_table . ' post ' . $joins . ' WHERE ' . $cond . ' ORDER BY id DESC LIMIT 100');
            while ($r = DB::row($q)) {
                // nacteni titulku, odkazu a strany
                $pagenum = null;
                $post_anchor = true;
                list($link, $title) = _linkPost($r);
                switch ($r['type']) {
                        // komentar sekce / prispevek knihy
                    case _post_section_comment:
                    case _post_book_entry:
                        $pagenum = _resultPagingGetItemPage(_commentsperpage, _posts_table, "id>" . $r['id'] . " AND type=" . $r['type'] . " AND xhome=-1 AND home=" . $r['home']);
                        break;

                        // komentar clanku
                    case _post_article_comment:
                        $pagenum = _resultPagingGetItemPage(_commentsperpage, _posts_table, "id>" . $r['id'] . " AND type=" . _post_article_comment . " AND xhome=-1 AND home=" . $r['home']);
                        break;

                        // prispevek na foru
                    case _post_forum_topic:
                        if ($r['xhome'] != -1) {
                            $pagenum = _resultPagingGetItemPage(_commentsperpage, _posts_table, "id<" . $r['id'] . " AND type=" . _post_forum_topic . " AND xhome=" . $r['xhome'] . " AND home=" . $r['home']);
                        } else {
                            $post_anchor = false;
                        }
                        break;
                }

                // sestaveni infa
                $infos = array();
                if ($r['author'] == -1) {
                    $infos[] = array(_lang('global.postauthor'), "<span class='post-author-guest'>" . $r['guest'] . '</span>');
                } else {
                    $infos[] = array(_lang('global.postauthor'), _linkUserFromQuery($userQuery, $r));
                }
                $infos[] = array(_lang('global.time'), _formatTime($r['time'], 'post'));

                // pridani do vysledku
                $results[] = array(
                    (isset($pagenum) ? _addGetToLink($link, 'page=' . $pagenum) : $link) . ($post_anchor ? '#post-' . $r['id'] : ''),
                    $title,
                    _cutText(strip_tags(_parsePost($r['text'])), 255),
                    $infos
                );
            }
            DB::free($q);
        }

        // vyhledani obrazku
        if ($image) {
            // zaklad dotazu
            $sql = 'SELECT img.id,img.prev,img.full,img.ord,img.home,img.title,gal.title AS gal_title,gal.slug,gal.var2 FROM ' . _images_table . ' AS img';

            // join na galerii
            $sql .= ' JOIN ' . _root_table . ' AS gal ON(gal.id=img.home)';

            // podminky
            $sql .= ' WHERE gal.level<=' . _priv_level . ' AND ';
            if ($public) {
                $sql .= 'gal.public=1 AND ';
            }
            $sql .= $searchQuery('img', array('title'));

            // vykonani a nacteni vysledku
            $q = DB::query($sql . ' LIMIT 100');
            while ($r = DB::row($q)) {
                $link = _addGetToLink(_linkRoot($r['home'], $r['slug']), 'page=' . _resultPagingGetItemPage($r['var2'] ?: _galdefault_per_page, _images_table, "ord<" . $r['ord'] . " AND home=" . $r['home']));
                $results[] = array(
                    $link,
                    $r['gal_title'],
                    (($r['title'] !== '') ? '<p>' . $r['title'] . '</p>' : '') . _galleryImage($r, 'search', _galdefault_thumb_w, _galdefault_thumb_h)
                );
            }
            DB::free($q);
        }

        // extend
        Extend::call('mod.search.results', array(
            'results' => &$results,
            'query' => $search_query,
            'query_sql' => $search_query_sql,
        ));

        // vypis vysledku
        if (count($results) != 0) {
            foreach ($results as $item) {
                $output .= "<div class='list-item'>
<h2 class='list-title'><a href='" . $item[0] . "'>" . $item[1] . "</a></h2>
<p class='list-perex'>" . $item[2] . "</p>
";
                if (isset($item[3])) {
                    $output .= _renderInfos($item[3]);
                }

                $output .= "</div>\n";
            }
        } else {
            $output .= _msg(_msg_ok, _lang('mod.search.noresult'));
        }
    } else {
        $output .= _msg(_msg_warn, _lang('mod.search.minlength'));
    }
}
