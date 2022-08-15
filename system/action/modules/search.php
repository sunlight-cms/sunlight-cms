<?php

use Sunlight\Article;
use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Gallery;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Util\UrlHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
    $_index->unauthorized();
    return;
}

if (!Settings::get('search')) {
    $_index->notFound();
    return;
}

/* ---  priprava  --- */

if (isset($_GET['q']) && Xsrf::check(true)) {
    $search_query = trim(Request::get('q', ''));
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

$_index->title = _lang('mod.search');

$output .= "
<p class='bborder'>" . _lang('mod.search.p') . "</p>

<form action='" . _e(Router::module('search')) . "' method='get' class='fullsearchform'>
" . (!Settings::get('pretty_urls') ? Form::renderHiddenInputs(Arr::filterKeys($_GET, null, null, ['q', 'page', 'art', 'post', 'img'])) : '') . "
<p><input type='search' name='q' class='inputmedium' value='" . _e($search_query) . "'> <input type='submit' value='" . _lang('mod.search.submit') . "'></p>
<p>
    " . _lang('mod.search.where') . ":
    <label><input type='checkbox' name='page' value='1'" . Form::activateCheckbox($page) . '> ' . _lang('mod.search.where.page') . "</label>
    <label><input type='checkbox' name='art' value='1'" . Form::activateCheckbox($art) . '> ' . _lang('mod.search.where.articles') . "</label>
    <label><input type='checkbox' name='post' value='1'" . Form::activateCheckbox($post) . '> ' . _lang('mod.search.where.posts') . "</label>
    <label><input type='checkbox' name='img' value='1'" . Form::activateCheckbox($image) . '> ' . _lang('mod.search.where.images') . '</label>
</p>

' . Xsrf::getInput() . '
</form>

';

/* ---  vyhledavani --- */

if ($search_query != '') {
    if (mb_strlen($search_query) >= 3) {
        // priprava
        $search_query_sql = DB::esc('%' . $search_query . '%');
        $results = []; // polozka: array(link, titulek, perex)
        $public = !User::isLoggedIn();

        // funkce na skladani vyhledavaciho dotazu
        $searchQuery = function ($alias, $cols) {
            if ($alias === null) {
                $alias = '';
            } else {
                $alias .= '.';
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
            $q = DB::query('SELECT id,title,slug,perex FROM ' . DB::table('page') . ' WHERE level<=' . User::getLevel() . ' AND ' . ($public ? 'public=1 AND ' : '') . $searchQuery(null, ['title', 'slug', 'description', 'perex', 'content']) . ' LIMIT 50');
            while ($r = DB::row($q)) {
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
            $q = DB::query('SELECT art.id,art.title,art.slug,art.perex,cat1.slug AS cat_slug FROM ' . DB::table('article') . ' art ' . $joins . ' WHERE ' . $cond . 'ORDER BY time DESC LIMIT 100');
            while ($r = DB::row($q)) {
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
            $types = [Post::SECTION_COMMENT, Post::ARTICLE_COMMENT, Post::BOOK_ENTRY, Post::FORUM_TOPIC, Post::PLUGIN];
            [$columns, $joins, $cond] = Post::createFilter('post', $types, [], $searchQuery('post', ['subject', 'text']));
            $userQuery = User::createQuery('post.author');
            $columns .= ',' . $userQuery['column_list'];
            $joins .= ' ' . $userQuery['joins'];

            // vykonani dotazu
            $q = DB::query($x = 'SELECT ' . $columns . ' FROM ' . DB::table('post') . ' post ' . $joins . ' WHERE ' . $cond . ' ORDER BY id DESC LIMIT 100');
            while ($r = DB::row($q)) {
                // nacteni titulku, odkazu a strany
                $pagenum = null;
                $post_anchor = true;
                [$link, $title] = Router::post($r);
                switch ($r['type']) {
                        // komentar sekce / prispevek knihy
                    case Post::SECTION_COMMENT:
                    case Post::BOOK_ENTRY:
                        $pagenum = Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id>' . $r['id'] . ' AND type=' . $r['type'] . ' AND xhome=-1 AND home=' . $r['home']);
                        break;

                        // komentar clanku
                    case Post::ARTICLE_COMMENT:
                        $pagenum = Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id>' . $r['id'] . ' AND type=' . Post::ARTICLE_COMMENT . ' AND xhome=-1 AND home=' . $r['home']);
                        break;

                        // prispevek na foru
                    case Post::FORUM_TOPIC:
                        if ($r['xhome'] != -1) {
                            $pagenum = Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id<' . $r['id'] . ' AND type=' . Post::FORUM_TOPIC . ' AND xhome=' . $r['xhome'] . ' AND home=' . $r['home']);
                        } else {
                            $post_anchor = false;
                        }
                        break;
                }

                // sestaveni infa
                $infos = [];
                if ($r['author'] == -1) {
                    $infos[] = [_lang('global.postauthor'), "<span class='post-author-guest'>" . PostService::renderGuestName($r['guest']) . '</span>'];
                } else {
                    $infos[] = [_lang('global.postauthor'), Router::userFromQuery($userQuery, $r)];
                }
                $infos[] = [_lang('global.time'), GenericTemplates::renderTime($r['time'], 'post')];

                // pridani do vysledku
                $results[] = [
                    (isset($pagenum) ? UrlHelper::appendParams($link, 'page=' . $pagenum) : $link) . ($post_anchor ? '#post-' . $r['id'] : ''),
                    $title,
                    StringManipulator::ellipsis(strip_tags(Post::render($r['text'])), 255),
                    $infos
                ];
            }
            DB::free($q);
        }

        // vyhledani obrazku
        if ($image) {
            // zaklad dotazu
            $sql = 'SELECT img.id,img.prev,img.full,img.ord,img.home,img.title,gal.title AS gal_title,gal.slug,gal.var2 FROM ' . DB::table('gallery_image') . ' AS img';

            // join na galerii
            $sql .= ' JOIN ' . DB::table('page') . ' AS gal ON(gal.id=img.home)';

            // podminky
            $sql .= ' WHERE gal.level<=' . User::getLevel() . ' AND ';
            if ($public) {
                $sql .= 'gal.public=1 AND ';
            }
            $sql .= $searchQuery('img', ['title']);

            // vykonani a nacteni vysledku
            $q = DB::query($sql . ' LIMIT 100');
            while ($r = DB::row($q)) {
                $link = Router::page($r['home'], $r['slug'], null, ['query' => ['page' => Paginator::getItemPage($r['var2'] ?: Settings::get('galdefault_per_page'), DB::table('gallery_image'), 'ord<' . $r['ord'] . ' AND home=' . $r['home'])]]);
                $results[] = [
                    $link,
                    $r['gal_title'],
                    (($r['title'] !== '') ? '<p>' . $r['title'] . '</p>' : '') . Gallery::renderImage($r, 'search', Settings::get('galdefault_thumb_w'), Settings::get('galdefault_thumb_h'))
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
<p class='list-perex'>" . $item[2] . '</p>
';
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
