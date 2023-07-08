<?php

namespace Sunlight\Post;

use Sunlight\Bbcode;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Settings;
use Sunlight\User;

abstract class Post
{
    /**
     * Section comment
     *
     * home:    page ID (section)
     * xhome:   post ID (if comment is an answer) or -1
     */
    const SECTION_COMMENT = 1;

    /**
     * Article comment:
     *
     * home:    article ID
     * xhome:   post ID (if comment is an answer) or -1
     */
    const ARTICLE_COMMENT = 2;

    /**
     * Book entry
     *
     * home:    page ID (book)
     * xhome:   post ID (if comment is an answer) or -1
     */
    const BOOK_ENTRY = 3;

    /**
     * Shoutbox entry:
     *
     * home:    shoutbox ID
     * xhome:   always -1
     */
    const SHOUTBOX_ENTRY = 4;

    /**
     * Forum topic
     *
     * home:    page ID (forum)
     * xhome:   post ID (if post is a reply) or -1 (if it is the main post)
     */
    const FORUM_TOPIC = 5;

    /**
     * Private message
     *
     * home:    pm ID
     * xhome:   pm ID (reply) or -1 (main post)
     */
    const PRIVATE_MSG = 6;

    /**
     * Plugin post
     *
     * home:    *plugin-implementation dependent*
     * xhome:   post ID (if post is an answer) or -1
     */
    const PLUGIN = 7;

    /**
     * Get maximum post text length
     */
    static function getMaxLength(int $type): int
    {
        if ($type === self::SHOUTBOX_ENTRY) {
            return 255;
        }

        return 16384;
    }

    /**
     * Check user access to the given post
     *
     * @param array $userQuery output of {@see User::createQuery()}
     * @param array $post post data (must contain user data and comment.time)
     */
    static function checkAccess(array $userQuery, array $post): bool
    {
        // user is logged in
        if (User::isLoggedIn()) {
            // extend
            $access = Extend::fetch('posts.access', [
                'post' => $post,
                'user_query' => $userQuery,
            ]);

            if ($access !== null) {
                return $access;
            }

            // is current user the author of the post?
            if (
                $post[$userQuery['prefix'] . 'id'] !== null
                && User::equals($post[$userQuery['prefix'] . 'id'])
                && (
                    $post['time'] + Settings::get('postadmintime') > time()
                    || User::hasPrivilege('unlimitedpostaccess')
                )
            ) {
                return true;
            }

            if (
                User::hasPrivilege('adminposts')
                && (
                    $post[$userQuery['prefix'] . 'group_level'] === null
                    || User::getLevel() > $post[$userQuery['prefix'] . 'group_level']
                )
            ) {
                // user can manage other people's posts
                return true;
            }
        }

        return false;
    }

    /**
     * Compose parts of SQL query to list posts
     *
     * Join aliases: home_page, home_art, home_cat1..3, home_post
     * Columns: post data, page|cat|art_title, page|cat|art_slug, xhome_subject
     *
     * @param string $alias post table alias
     * @param array $types list of post types to load
     * @param array $homes list of home IDs
     * @param string|null $sqlConditions custom SQL conditions
     * @param bool $doCount return a post count as well 1/0
     * @return array column_string, join_string, where_string, [count]
     */
    static function createFilter(string $alias, array $types = [], array $homes = [], ?string $sqlConditions = null, bool $doCount = false): array
    {
        // columns
        $columns = "{$alias}.id,{$alias}.type,{$alias}.home,{$alias}.xhome,{$alias}.subject,
{$alias}.author,{$alias}.guest,{$alias}.time,{$alias}.text,{$alias}.flag,
home_page.title page_title,home_page.slug page_slug,
home_cat1.title cat_title,home_cat1.slug cat_slug,
home_art.title art_title,home_art.slug art_slug,
home_post.subject xhome_subject";

        // conditions
        $conditions = [];

        if (!empty($types)) {
            $conditions[] = "{$alias}.type IN(" . DB::arr($types) . ')';
        }

        if (!empty($homes)) {
            $conditions[] = "{$alias}.home IN(" . DB::arr($homes) . ')';
        }

        $conditions[] = '(home_page.id IS NULL OR ' . (User::isLoggedIn() ? '' : 'home_page.public=1 AND ') . 'home_page.level<=' . User::getLevel() . ')
AND (home_art.id IS NULL OR ' . (User::isLoggedIn() ? '' : 'home_art.public=1 AND ') . 'home_art.time<=' . time() . " AND home_art.confirmed=1)
AND ({$alias}.type!=" . self::ARTICLE_COMMENT . ' OR (
    ' . (User::isLoggedIn() ? '' : '(home_cat1.public=1 OR home_cat2.public=1 OR home_cat3.public=1) AND') . '
    (home_cat1.level<=' . User::getLevel() . ' OR home_cat2.level<=' . User::getLevel() . ' OR home_cat3.level<=' . User::getLevel() . ')
))';

        // custom conditions
        if (!empty($sqlConditions)) {
            $conditions[] = $sqlConditions;
        }

        // joins
        $joins = 'LEFT JOIN ' . DB::table('page') . " home_page ON({$alias}.type IN(1,3,5) AND {$alias}.home=home_page.id)
LEFT JOIN " . DB::table('article') . " home_art ON({$alias}.type=" . self::ARTICLE_COMMENT . " AND {$alias}.home=home_art.id)
LEFT JOIN " . DB::table('page') . " home_cat1 ON({$alias}.type=" . self::ARTICLE_COMMENT . ' AND home_art.home1=home_cat1.id)
LEFT JOIN ' . DB::table('page') . " home_cat2 ON({$alias}.type=" . self::ARTICLE_COMMENT . ' AND home_art.home2!=-1 AND home_art.home2=home_cat2.id)
LEFT JOIN ' . DB::table('page') . " home_cat3 ON({$alias}.type=" . self::ARTICLE_COMMENT . ' AND home_art.home3!=-1 AND home_art.home3=home_cat3.id)
LEFT JOIN ' . DB::table('post') . " home_post ON({$alias}.type=" . self::FORUM_TOPIC . " AND {$alias}.xhome!=-1 AND {$alias}.xhome=home_post.id)";

        // extend
        Extend::call('posts.filter', [
            'columns' => &$columns,
            'joins' => &$joins,
            'conditions' => &$conditions,
            'alias' => $alias,
        ]);

        // result
        $result = [
            $columns,
            $joins,
            implode(' AND ', $conditions),
        ];

        // add count
        if ($doCount) {
            $result[] = (int) DB::result(DB::query("SELECT COUNT({$alias}.id) FROM " . DB::table('post') . " {$alias} {$joins} WHERE {$result[2]}"));
        }

        return $result;
    }

    /**
     * Render post text
     *
     * @param string $input input text (HTML)
     * @param bool $bbcode parse BBCode 1/0
     * @param bool $nl2br convert newlines to <br>
     */
    static function render(string $input, bool $bbcode = true, bool $nl2br = true): string
    {
        // event
        Extend::call('post.render', [
            'input' => &$input,
            'bbcode' => &$bbcode,
            'nl2br' => &$nl2br,
        ]);

        // parse BBCode
        if (Settings::get('bbcode') && $bbcode) {
            $input = Bbcode::parse($input);
        }

        // convert newlines
        if ($nl2br) {
            $input = nl2br($input, false);
        }

        return $input;
    }
}
