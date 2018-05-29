<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;

defined('_root') or exit;

// titulek
$_index['title'] = $_page['title'];

// obsah
Extend::call('page.group.content.before', $extend_args);
if ($_page['content'] != "") {
    $output .= \Sunlight\HCM::parse($_page['content']) . "\n\n<div class='hr group-hr'><hr></div>\n\n";
}
Extend::call('page.group.content.after', $extend_args);

// vypis polozek
$items = DB::query("SELECT id,title,slug,type,type_idt,perex,var1 FROM " . _root_table . " WHERE node_parent=" . $id . " AND visible=1 ORDER BY ord");
if (DB::size($items) != 0) {
    while ($item = DB::row($items)) {
        $extendArgs = Extend::args($output, array('item' => &$item));

        $output .= "<div class='list-item'>\n";

        Extend::call('page.group.item.start', $extendArgs);

        // titulek
        $output .= "<h2 class='list-title'><a href='" . _linkRoot($item['id'], $item['slug']) . "'" . (($item['type'] == _page_link && $item['var1'] == 1) ? " target='_blank'" : '') . ">" . $item['title'] . "</a></h2>\n";

        Extend::call('page.group.item.title.after', $extendArgs);

        // perex
        if ($item['perex'] != "") {
            $output .= "<p class='list-perex'>" . $item['perex'] . "</p>\n";
        }

        Extend::call('page.group.item.perex.after', $extendArgs);

        // informace
        if ($_page['var1'] == 1) {
            $iteminfos = array();

            switch ($item['type']) {
                    // sekce
                case _page_section:
                    if ($item['var1'] == 1) {
                        $iteminfos['comment_num'] = array(_lang('article.comments'), DB::count(_posts_table, 'type=' . _post_section_comment . ' AND home=' . DB::val($item['id'])));
                    }
                    break;

                    // kategorie
                case _page_category:
                    list(, , $art_count) = _articleFilter('art', array($item['id']), null, true);
                    $iteminfos['article_num'] = array(_lang('global.articlesnum'), $art_count);
                    break;

                    // kniha
                case _page_book:
                    // nacteni jmena autora posledniho prispevku
                    $userQuery = _userQuery('p.author');
                    $lastpost = DB::query("SELECT p.author,p.guest," . $userQuery['column_list'] . " FROM " . _posts_table . " p " . $userQuery['joins'] . " WHERE p.home=" . $item['id'] . " ORDER BY p.id DESC LIMIT 1");
                    if ($lastpost !== false) {
                        if ($lastpost['author'] != -1) {
                            $lastpost = _linkUserFromQuery($userQuery, $lastpost);
                        } else {
                            $lastpost = $lastpost['guest'];
                        }
                    } else {
                        $lastpost = "-";
                    }

                    $iteminfos['post_num'] = array(_lang('global.postsnum'), DB::count(_posts_table, 'type=' . _post_book_entry . ' AND home=' . DB::val($item['id'])));
                    $iteminfos['last_post'] = array(_lang('global.lastpost'), $lastpost);
                    break;

                    // galerie
                case _page_gallery:
                    $iteminfos['image_num'] = array(_lang('global.imgsnum'), DB::count(_images_table, 'home=' . DB::val($item['id'])));
                    break;

                    // forum
                case _page_forum:
                    $iteminfos['topic_num'] = array(_lang('global.topicsnum'), DB::count(_posts_table, 'type=' . _post_forum_topic . ' AND home=' . DB::val($item['id']) . ' AND xhome=-1'));
                    $iteminfos['answer_num'] = array(_lang('global.answersnum'), DB::count(_posts_table, 'type=' . _post_forum_topic . ' AND home=' . DB::val($item['id']) . ' AND xhome!=-1'));
                    break;

                    // plugin stranka
                case _page_plugin:
                    Extend::call('page.plugin.' . $item['type_idt'] . '.group_infos', array('item' => $item, 'infos' => &$iteminfos));
                    break;
            }

            Extend::call('page.group.item_infos', array('item' => $item, 'infos' => &$iteminfos));

            $output .= _renderInfos($iteminfos);

            Extend::call('page.group.item.end', $extendArgs);
        }

        $output .= "</div>\n";
    }
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
