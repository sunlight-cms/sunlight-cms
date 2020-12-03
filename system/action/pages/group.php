<?php

use Sunlight\Article;
use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Hcm;
use Sunlight\Router;
use Sunlight\User;

defined('_root') or exit;

// titulek
$_index['title'] = $_page['title'];

// obsah
Extend::call('page.group.content.before', $extend_args);
if ($_page['content'] != "") {
    $output .= Hcm::parse($_page['content']) . "\n\n<div class='hr group-hr'><hr></div>\n\n";
}
Extend::call('page.group.content.after', $extend_args);

// vypis polozek
$items = DB::query("SELECT id,title,slug,type,type_idt,perex,var1 FROM " . _page_table . " WHERE node_parent=" . $id . " AND visible=1 ORDER BY ord");
if (DB::size($items) != 0) {
    while ($item = DB::row($items)) {
        $extendArgs = Extend::args($output, ['item' => &$item]);

        $output .= "<div class='list-item'>\n";

        Extend::call('page.group.item.start', $extendArgs);

        // titulek
        $output .= "<h2 class='list-title'><a href='" . Router::page($item['id'], $item['slug']) . "'" . (($item['type'] == _page_link && $item['var1'] == 1) ? " target='_blank'" : '') . ">" . $item['title'] . "</a></h2>\n";

        Extend::call('page.group.item.title.after', $extendArgs);

        // perex
        if ($item['perex'] != "") {
            $output .= "<p class='list-perex'>" . $item['perex'] . "</p>\n";
        }

        Extend::call('page.group.item.perex.after', $extendArgs);

        // informace
        if ($_page['var1'] == 1) {
            $iteminfos = [];

            switch ($item['type']) {
                    // sekce
                case _page_section:
                    if ($item['var1'] == 1) {
                        $iteminfos['comment_num'] = [_lang('article.comments'), DB::count(_comment_table, 'type=' . _post_section_comment . ' AND home=' . DB::val($item['id']))];
                    }
                    break;

                    // kategorie
                case _page_category:
                    list(, , $art_count) = Article::createFilter('art', [$item['id']], null, true);
                    $iteminfos['article_num'] = [_lang('global.articlesnum'), $art_count];
                    break;

                    // kniha
                case _page_book:
                    // nacteni jmena autora posledniho prispevku
                    $userQuery = User::createQuery('p.author');
                    $lastpost = DB::queryRow("SELECT p.author,p.guest," . $userQuery['column_list'] . " FROM " . _comment_table . " p " . $userQuery['joins'] . " WHERE p.home=" . $item['id'] . " ORDER BY p.id DESC LIMIT 1");
                    if ($lastpost !== false) {
                        if ($lastpost['author'] != -1) {
                            $lastpost = Router::userFromQuery($userQuery, $lastpost);
                        } else {
                            $lastpost = CommentService::renderGuestName($lastpost['guest']);
                        }
                    } else {
                        $lastpost = "-";
                    }

                    $iteminfos['post_num'] = [_lang('global.postsnum'), DB::count(_comment_table, 'type=' . _post_book_entry . ' AND home=' . DB::val($item['id']))];
                    $iteminfos['last_post'] = [_lang('global.lastpost'), $lastpost];
                    break;

                    // galerie
                case _page_gallery:
                    $iteminfos['image_num'] = [_lang('global.imgsnum'), DB::count(_gallery_image_table, 'home=' . DB::val($item['id']))];
                    break;

                    // forum
                case _page_forum:
                    $iteminfos['topic_num'] = [_lang('global.topicsnum'), DB::count(_comment_table, 'type=' . _post_forum_topic . ' AND home=' . DB::val($item['id']) . ' AND xhome=-1')];
                    $iteminfos['answer_num'] = [_lang('global.answersnum'), DB::count(_comment_table, 'type=' . _post_forum_topic . ' AND home=' . DB::val($item['id']) . ' AND xhome!=-1')];
                    break;

                    // plugin stranka
                case _page_plugin:
                    Extend::call('page.plugin.' . $item['type_idt'] . '.group_infos', ['item' => $item, 'infos' => &$iteminfos]);
                    break;
            }

            Extend::call('page.group.item_infos', ['item' => $item, 'infos' => &$iteminfos]);

            $output .= GenericTemplates::renderInfos($iteminfos);

            Extend::call('page.group.item.end', $extendArgs);
        }

        $output .= "</div>\n";
    }
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
