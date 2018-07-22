<?php

use Sunlight\Article;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Picture;
use Sunlight\Comment\Comment;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\Response;

require '../bootstrap.php';
Core::init('../../', array(
    'content_type' => false,
));

if (!_rss) {
    exit;
}

/* ---  priprava promennych  --- */

$continue = false;
$type = (int) Request::get('tp');
$id = (int) Request::get('id');
$template = Template::getCurrent();

// cast sql dotazu - pristup ke strance
$page_cond = 'level<=' . _priv_level;
$page_joined_cond = 'page.level<=' . _priv_level;
if (!_logged_in) {
    if (_notpublicsite) {
        exit;
    } else {
        $page_cond = 'public=1 AND ' . $page_cond;
        $page_joined_cond = 'page.public=1 AND ' . $page_joined_cond;
    }
}

// nastaveni
$image_url = $template->getWebPath(true) . '/images/system/rss-logo.gif';
$image_w = 80;
$image_h = 80;
$feed_url = Core::$url;
$feed_descr = _description;

// promenne
$donottestsource = false;
$post_homes = array($id);
$post_cond = null;
$pagetitle_column = 'title';
$custom_cond = true;

// priprava dle typu
switch ($type) {
        // komentare v sekci a prispevky v knize
    case _rss_section_comments:
    case _rss_book_posts:
        $query = DB::query("SELECT title,slug FROM " . _page_table . " WHERE type=" . $type . ($type == _rss_section_comments ? " AND var1=1" : '') . ' AND ' . $page_cond . " AND id=" . $id);
        $feed_title = _lang($type == _rss_section_comments ? 'rss.recentcomments' : 'rss.recentposts');
        $post_types = array($type);
        break;

        // komentare u clanku
    case _rss_article_comments:
        $query = DB::queryRow("SELECT art.id,art.time,art.confirmed,art.author,art.public,art.home1,art.home2,art.home3,art.title,art.slug,art.picture_uid,cat.slug cat_slug FROM " . _article_table . " art JOIN " . _page_table . " cat ON(art.home1=cat.id) WHERE art.id=" . $id . " AND art.comments=1");
        $donottestsource = true;

        // test pristupu k clanku
        $custom_cond = false;
        if ($query !== false) {
            $custom_cond = Article::checkAccess($query);

            // obrazek clanku
            if ($query['picture_uid']) {
                $image_url = Router::file(Picture::get(_root . 'images/articles/', null, $query['picture_uid'], 'jpg'));
                $image_w = _article_pic_w;
                $image_h = _article_pic_h;
            }

            $feed_url = Router::article($id, $query['slug'], $query['cat_slug'], true);
            $feed_title = _lang('rss.recentcomments');
            $post_types = array(_post_article_comment);
        }

        break;

        // nejnovejsi clanky
    case _rss_latest_articles:
        if ($id != -1) {
            $query = DB::query("SELECT title,slug FROM " . _page_table . " WHERE type=" . _page_category . " AND " . $page_cond . " AND id=" . $id);
            $categories = array($id);
        } else {
            $donottestsource = true;
            $query = array("title" => null);
            $categories = array();
        }

        $feed_title = _lang('rss.recentarticles');
        break;

        // nejnovejsi temata
    case _rss_latest_topics:
        $query = DB::query("SELECT title,slug FROM " . _page_table . " WHERE type=" . _page_forum . " AND " . $page_cond . " AND id=" . $id);
        $feed_title = _lang('rss.recenttopics');
        $post_types = array(_post_forum_topic);
        $post_cond = "post.xhome=-1";
        break;

        // nejnovejsi odpovedi na tema
    case _rss_latest_topic_answers:
        $query = DB::query("SELECT topic.subject FROM " . _comment_table . " topic JOIN " . _page_table . " page ON(page.id=topic.home) WHERE topic.type=" . _post_forum_topic . " AND topic.id=" . $id . " AND " . $page_joined_cond);
        $feed_title = _lang('rss.recentanswers');
        $post_cond = "post.xhome=" . $id;
        $post_types = array(_post_forum_topic);
        $post_homes = array();
        $pagetitle_column = "subject";
        break;

        // nejnovejsi komentare (globalne)
    case _rss_latest_comments:
        if (!_comments) {
            exit;
        }
        $query = array("title" => null);
        $feed_title = _lang('rss.recentcomments');
        $post_types = array(_post_section_comment, _post_article_comment, _post_book_entry, _post_forum_topic, _post_plugin);
        $post_homes = array();
        $donottestsource = true;
        break;

        // nelegalni typ
    default:
        Response::notFound();
        exit;
}

// nacteni polozek
if ($custom_cond && ($donottestsource || DB::size($query) != 0)) {
    $feeditems = array();
    if (!$donottestsource) {
        $query = DB::row($query);
    }
    $pagetitle = $query[$pagetitle_column];

    switch ($type) {
            // komentare/prispevky/temata
        case _rss_section_comments:
        case _rss_article_comments:
        case _rss_book_posts:
        case _rss_latest_topics:
        case _rss_latest_topic_answers:
        case _rss_latest_comments:
            list($columns, $joins, $cond) = Comment::createFilter('post', $post_types, $post_homes);
            $userQuery = User::createQuery('post.author');
            $columns .= ',' . $userQuery['column_list'];
            $joins .= ' ' . $userQuery['joins'];
            if ($post_cond !== null) {
                $cond .= " AND " . $post_cond;
            }
            $items = DB::query("SELECT " . $columns . " FROM " . _comment_table . " post " . $joins . " WHERE " . $cond . " ORDER BY post.id DESC LIMIT " . _rsslimit);
            while ($item = DB::row($items)) {

                // nacteni jmena autora
                if ($item['author'] != -1) {
                    $author = Router::userFromQuery($userQuery, $item, array('plain' => true));
                } else {
                    $author = $item['guest'];
                }

                // odkaz na stranku
                list($homelink, $hometitle) = Router::post($item, true, true);

                // sestaveni titulku
                if ($type == _rss_latest_comments) {
                    $title = "{$hometitle}, {$author}";
                } else {
                    $title = $author;
                }
                if ($item['subject'] != '' && $item['subject'] != '-') {
                    $title .= ": {$item['subject']}";
                }

                // ulozeni zaznamu
                $feeditems[] = array(
                    $title,
                    $homelink . "#posts",
                    Html::cut(strip_tags(Comment::render($item['text'])), 255),
                    $item['time']
                );

            }
            break;

            // nejnovejsi clanky
        case _rss_latest_articles:
            list($joins, $cond) = Article::createFilter('art', $categories);
            $items = DB::query("SELECT art.id,art.time,art.confirmed,art.public,art.home1,art.home2,art.home3,art.title,art.slug,art.perex,cat1.slug AS cat_slug FROM " . _article_table . " AS art " . $joins . " WHERE " . $cond . " ORDER BY art.time DESC LIMIT " . _rsslimit);
            while ($item = DB::row($items)) {
                $feeditems[] = array(
                    $item['title'],
                    Router::article($item['id'], $item['slug'], $item['cat_slug'], true),
                    strip_tags($item['perex']),
                    $item['time']
                );
            }
            break;
    }

    $continue = true;
}

/* ---  vystup  --- */

if ($continue) {
    header("Content-Type: application/xml; charset=UTF-8");
    $main_title = _title . ' ' . _titleseparator . (($pagetitle != null) ? ' ' . $pagetitle . ' ' . _titleseparator : '') . ' ' . $feed_title;

    $cdata = function ($string) {
        return '<![CDATA[' . str_replace(']]>', ']]&gt;', $string) . ']]>';
    };

    echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="0.91">
  <channel>
    <title>' . $cdata($main_title) . '</title>
    <link>' . $cdata($feed_url) . '</link>
    <description>' . $cdata($feed_descr) . '</description>
    <language>' . $cdata(_lang('langcode.iso639')) . '</language>
    <image>
      <title>' . $cdata($feed_title) . '</title>
      <url>' . $cdata($image_url) . '</url>
      <link>' . $cdata($feed_url) . '</link>
      <width>' . $cdata($image_w) . '</width>
      <height>' . $cdata($image_h) . '</height>
    </image>
';

    // polozky
    foreach ($feeditems as $feeditem) {
        echo '
    <item>
       <title>' . $cdata($feeditem[0]) . '</title>
       <link>' . $cdata($feeditem[1]) . '</link>
       <pubDate>' . date('r', $feeditem[3]) . '</pubDate>
       <description>' . $cdata($feeditem[2]) . '</description>
    </item>
  ';
    }

    echo '
  </channel>
</rss>';
}
