<?php

use Sunlight\Article;
use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Gallery;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\Comment\Comment;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Util\UrlHelper;
use Sunlight\Xsrf;

defined('_root') or exit;

if (!_logged_in && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

if (!_search) {
    $_index['is_found'] = false;
    return;
}

/* ---  priprava  --- */

if (isset($_GET['q']) && Xsrf::check(true)) {
    $search_query = trim(Request::get('q'));
    $page = isset($_GET['page']);
    $art = isset($_GET['art']);
    $post = isset($_GET['post']);
    $image = isset($_GET['img']);
} else {
    $search_query = '';
    $page = true;
    $art = true;
    $post = true;
    $image = true;
}

/* ---  modul  --- */

$_index['title'] = _lang('mod.search');

$output .= "
<p class='bborder'>" . _lang('mod.search.p') . "</p>

<form action='" . _e(Router::module('search')) . "' method='get' class='fullsearchform'>
" . (!_pretty_urls ? Form::renderHiddenInputs(Arr::filterKeys($_GET, null, null, ['q', 'page', 'art', 'post', 'img'])) : '') . "
<p><input type='search' name='q' class='inputmedium' value='" . _e($search_query) . "'> <input type='submit' value='" . _lang('mod.search.submit') . "'></p>
<p>
    " . _lang('mod.search.where') . ":
    <label><input type='checkbox' name='page' value='1'" . Form::activateCheckbox($page) . "> " . _lang('mod.search.where.page') . "</label>
    <label><input type='checkbox' name='art' value='1'" . Form::activateCheckbox($art) . "> " . _lang('mod.search.where.articles') . "</label>
    <label><input type='checkbox' name='post' value='1'" . Form::activateCheckbox($post) . "> " . _lang('mod.search.where.posts') . "</label>
    <label><input type='checkbox' name='img' value='1'" . Form::activateCheckbox($image) . "> " . _lang('mod.search.where.images') . "</label>
</p>

" . Xsrf::getInput() . "
</form>

";

/* ---  vyhledavani --- */

if ($search_query != '') {
    if (mb_strlen($search_query) >= 3) {
        // priprava
        $search_query_sql = DB::esc('%' . $search_query . '%');
        $results = []; // polozka: array(link, titulek, perex)
        $public = !_logged_in;

        // funkce na skladani vyhledavaciho dotazu
        $searchQuery = function ($alias, $cols) {
            if ($alias === null) {
                $alias = '';
            } else {
                $alias = $alias . '.';
            }
            $output = '(';
            for ($i = 0, $last = (count($cols) - 1); isset($cols[$i]); ++$i) {
                $output .= $alias . $cols[$i] . ' LIKE \'' . $GLOBALS['search_query_sql'] . '\'';
                if ($i !== $last) {
                    $output .= ' OR ';
                }
            }
            $output .= ')';

            return $output;
        };

        // vyhledani stranek
        if ($page) {
            $q = DB::query('SELECT id,title,slug,perex FROM ' . _page_table . ' WHERE level<=' . _priv_level . ' AND ' . ($public ? 'public=1 AND ' : '') . $searchQuery(null, ['title', 'slug', 'description', 'perex', 'content']) . ' LIMIT 50');
            while($r = DB::row($q)) {
                $results[] = [
                    Router::page($r['id'], $r['slug']),
                    $r['title'],
                    strip_tags($r['perex'])
                ];
            }
            DB::free($q);
        }

        // vyhledani clanku
        if ($art) {
            // zakladni dostaz
            [$joins, $cond] = Article::createFilter('art', [], $searchQuery('art', ['title', 'slug', 'perex', 'description', 'content']));

            // vykonani a nacteni vysledku
            $q = DB::query('SELECT art.id,art.title,art.slug,art.perex,cat1.slug AS cat_slug FROM ' . _article_table . ' art ' . $joins . ' WHERE ' . $cond . 'ORDER BY time DESC LIMIT 100');
            while($r = DB::row($q)) {
                $results[] = [
                    Router::article($r['id'], $r['slug'], $r['cat_slug']),
                    $r['title'],
                    StringManipulator::ellipsis(strip_tags($r['perex']), 255, false)
                ];
            }
            DB::free($q);
        }

        // vyhledani prispevku
        if ($post) {
            // priprava
            $types = [_post_section_comment, _post_article_comment, _post_book_entry, _post_forum_topic, _post_plugin];
            [$columns, $joins, $cond] = Comment::createFilter('post', $types, [], $searchQuery('post', ['subject', 'text']));
            $userQuery = User::createQuery('post.author');
            $columns .= ',' . $userQuery['column_list'];
            $joins .= ' ' . $userQuery['joins'];

            // vykonani dotazu
            $q = DB::query($x = 'SELECT ' . $columns . ' FROM ' . _comment_table . ' post ' . $joins . ' WHERE ' . $cond . ' ORDER BY id DESC LIMIT 100');
            while ($r = DB::row($q)) {
                // nacteni titulku, odkazu a strany
                $pagenum = null;
                $post_anchor = true;
                [$link, $title] = Router::post($r);
                switch ($r['type']) {
                        // komentar sekce / prispevek knihy
                    case _post_section_comment:
                    case _post_book_entry:
                        $pagenum = Paginator::getItemPage(_commentsperpage, _comment_table, "id>" . $r['id'] . " AND type=" . $r['type'] . " AND xhome=-1 AND home=" . $r['home']);
                        break;

                        // komentar clanku
                    case _post_article_comment:
                        $pagenum = Paginator::getItemPage(_commentsperpage, _comment_table, "id>" . $r['id'] . " AND type=" . _post_article_comment . " AND xhome=-1 AND home=" . $r['home']);
                        break;

                        // prispevek na foru
                    case _post_forum_topic:
                        if ($r['xhome'] != -1) {
                            $pagenum = Paginator::getItemPage(_commentsperpage, _comment_table, "id<" . $r['id'] . " AND type=" . _post_forum_topic . " AND xhome=" . $r['xhome'] . " AND home=" . $r['home']);
                        } else {
                            $post_anchor = false;
                        }
                        break;
                }

                // sestaveni infa
                $infos = [];
                if ($r['author'] == -1) {
                    $infos[] = [_lang('global.postauthor'), "<span class='post-author-guest'>" . CommentService::renderGuestName($r['guest']) . '</span>'];
                } else {
                    $infos[] = [_lang('global.postauthor'), Router::userFromQuery($userQuery, $r)];
                }
                $infos[] = [_lang('global.time'), GenericTemplates::renderTime($r['time'], 'post')];

                // pridani do vysledku
                $results[] = [
                    (isset($pagenum) ? UrlHelper::appendParams($link, 'page=' . $pagenum) : $link) . ($post_anchor ? '#post-' . $r['id'] : ''),
                    $title,
                    StringManipulator::ellipsis(strip_tags(Comment::render($r['text'])), 255),
                    $infos
                ];
            }
            DB::free($q);
        }

        // vyhledani obrazku
        if ($image) {
            // zaklad dotazu
            $sql = 'SELECT img.id,img.prev,img.full,img.ord,img.home,img.title,gal.title AS gal_title,gal.slug,gal.var2 FROM ' . _gallery_image_table . ' AS img';

            // join na galerii
            $sql .= ' JOIN ' . _page_table . ' AS gal ON(gal.id=img.home)';

            // podminky
            $sql .= ' WHERE gal.level<=' . _priv_level . ' AND ';
            if ($public) {
                $sql .= 'gal.public=1 AND ';
            }
            $sql .= $searchQuery('img', ['title']);

            // vykonani a nacteni vysledku
            $q = DB::query($sql . ' LIMIT 100');
            while ($r = DB::row($q)) {
                $link = UrlHelper::appendParams(Router::page($r['home'], $r['slug']), 'page=' . Paginator::getItemPage($r['var2'] ?: _galdefault_per_page, _gallery_image_table, "ord<" . $r['ord'] . " AND home=" . $r['home']));
                $results[] = [
                    $link,
                    $r['gal_title'],
                    (($r['title'] !== '') ? '<p>' . $r['title'] . '</p>' : '') . Gallery::renderImage($r, 'search', _galdefault_thumb_w, _galdefault_thumb_h)
                ];
            }
            DB::free($q);
        }

        // extend
        Extend::call('mod.search.results', [
            'results' => &$results,
            'query' => $search_query,
            'query_sql' => $search_query_sql,
        ]);

        // vypis vysledku
        if (count($results) != 0) {
            foreach ($results as $item) {
                $output .= "<div class='list-item'>
<h2 class='list-title'><a href='" . _e($item[0]) . "'>" . $item[1] . "</a></h2>
<p class='list-perex'>" . $item[2] . "</p>
";
                if (isset($item[3])) {
                    $output .= GenericTemplates::renderInfos($item[3]);
                }

                $output .= "</div>\n";
            }
        } else {
            $output .= Message::ok(_lang('mod.search.noresult'));
        }
    } else {
        $output .= Message::warning(_lang('mod.search.minlength'));
    }
}
