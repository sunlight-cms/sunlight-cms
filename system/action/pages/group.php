<?php

use Sunlight\Article;
use Sunlight\Post\Post;
use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Hcm;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\User;

defined('SL_ROOT') or exit;

// title
$_index->title = $_page['title'];

// content
Extend::call('page.group.content.before', $extend_args);

if ($_page['content'] != '') {
    $output .= Hcm::parse($_page['content']) . "\n\n<div class=\"hr group-hr\"><hr></div>\n\n";
}

Extend::call('page.group.content.after', $extend_args);

// child pages
$items = DB::query('SELECT id,title,slug,type,type_idt,perex,var1 FROM ' . DB::table('page') . ' WHERE node_parent=' . $id . ' AND visible=1 ORDER BY ord');

if (DB::size($items) != 0) {
    $output .= "<div class=\"list list-pages\">\n";

    while ($item = DB::row($items)) {
        $extendArgs = Extend::args($output, ['item' => &$item]);

        $output .= "<div class=\"list-item\">\n";

        Extend::call('page.group.item.start', $extendArgs);

        // title
        $output .= '<h2 class="list-title"><a href="' . _e(Router::page($item['id'], $item['slug'])) . '"' . (($item['type'] == Page::LINK && $item['var1'] == 1) ? ' target="_blank"' : '') . '>' . $item['title'] . "</a></h2>\n";

        Extend::call('page.group.item.title.after', $extendArgs);

        // perex
        if ($item['perex'] != '') {
            $output .= '<p class="list-perex">' . $item['perex'] . "</p>\n";
        }

        Extend::call('page.group.item.perex.after', $extendArgs);

        // infos
        if ($_page['var1'] == 1) {
            $iteminfos = [];

            switch ($item['type']) {
                // section
                case Page::SECTION:
                    if ($item['var1'] == 1) {
                        $iteminfos['comment_num'] = [
                            _lang('article.comments'),
                             _num(DB::count('post', 'type=' . Post::SECTION_COMMENT . ' AND home=' . DB::val($item['id']))),
                        ];
                    }
                    break;

                // category
                case Page::CATEGORY:
                    [, , $art_count] = Article::createFilter('art', [$item['id']], null, true);
                    $iteminfos['article_num'] = [
                        _lang('global.articlesnum'),
                        _num($art_count),
                    ];
                    break;

                // book
                case Page::BOOK:
                    // load author of last post
                    $userQuery = User::createQuery('p.author');
                    $lastpost = DB::queryRow('SELECT p.author,p.guest,' . $userQuery['column_list'] . ' FROM ' . DB::table('post') . ' p ' . $userQuery['joins'] . ' WHERE p.home=' . $item['id'] . ' ORDER BY p.id DESC LIMIT 1');

                    if ($lastpost !== false) {
                        if ($lastpost['author'] != -1) {
                            $lastpost = Router::userFromQuery($userQuery, $lastpost);
                        } else {
                            $lastpost = PostService::renderGuestName($lastpost['guest']);
                        }
                    } else {
                        $lastpost = '-';
                    }

                    $iteminfos['post_num'] = [
                        _lang('global.postsnum'), 
                        _num(DB::count('post', 'type=' . Post::BOOK_ENTRY . ' AND home=' . DB::val($item['id']))),
                    ];
                    $iteminfos['last_post'] = [_lang('global.lastpost'), $lastpost];
                    break;

                // gallery
                case Page::GALLERY:
                    $iteminfos['image_num'] = [
                        _lang('global.imgsnum'), 
                        _num(DB::count('gallery_image', 'home=' . DB::val($item['id']))),
                    ];
                    break;

                // forum
                case Page::FORUM:
                    $iteminfos['topic_num'] = [
                        _lang('global.topicsnum'), 
                        _num(DB::count('post', 'type=' . Post::FORUM_TOPIC . ' AND home=' . DB::val($item['id']) . ' AND xhome=-1')),
                    ];
                    $iteminfos['answer_num'] = [
                        _lang('global.answersnum'), 
                        _num(DB::count('post', 'type=' . Post::FORUM_TOPIC . ' AND home=' . DB::val($item['id']) . ' AND xhome!=-1')),
                    ];
                    break;

                // plugin page
                case Page::PLUGIN:
                    Extend::call('page.plugin.' . $item['type_idt'] . '.group_infos', ['item' => $item, 'infos' => &$iteminfos]);
                    break;
            }

            Extend::call('page.group.item_infos', ['item' => $item, 'infos' => &$iteminfos]);

            $output .= GenericTemplates::renderInfos($iteminfos);

            Extend::call('page.group.item.end', $extendArgs);
        }

        $output .= "</div>\n";
    }

    $output .= "</div>\n";
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
