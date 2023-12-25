<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Image\ImageException;
use Sunlight\Image\ImageLoader;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageStorage;
use Sunlight\Image\ImageTransformer;
use Sunlight\Util\Arr;
use Sunlight\Util\StringGenerator;

abstract class Article
{
    /**
     * See if the current user can access an article
     *
     * @param array $article article data (id,time,confirmed,author,public,home1,home2,home3)
     * @param bool $checkCategories check access to categories 1/0
     */
    static function checkAccess(array $article, bool $checkCategories = true): bool
    {
        // unconfirmed or unpublished article
        if (!$article['confirmed'] || $article['time'] > time()) {
            return User::hasPrivilege('adminconfirm') || User::equals($article['author']);
        }

        // access to article
        if (!User::checkPublicAccess($article['public'])) {
            return false;
        }

        // access to at least one category
        if ($checkCategories) {
            $categoryIds = Arr::removeValue([$article['home1'], $article['home2'], $article['home3']], -1);
            $hasCategoryAccess = false;
            $result = DB::query('SELECT public,level FROM ' . DB::table('page') . ' WHERE id IN(' . DB::arr($categoryIds) . ')');

            while ($r = DB::row($result)) {
                if (User::checkPublicAccess($r['public'], $r['level'])) {
                    $hasCategoryAccess = true;
                    break;
                }
            }

            if (!$hasCategoryAccess) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find an article and load its data
     *
     * The returned array contains:
     *
     * - article columns
     * - category columns: cat[123]_{id,title,slug,public,level}
     * - author_query + columns from {@see User::createQuery()}
     *
     * @param string $slug article slug
     * @param int|null $mainCategoryId main category ID (home1)
     * @return array|false
     */
    static function find(string $slug, ?int $mainCategoryId = null)
    {
        $authorQuery = User::createQuery('a.author');

        $sql = 'SELECT a.*';

        for ($i = 1; $i <= 3; ++$i) {
            $sql .= ",cat{$i}.id cat{$i}_id,cat{$i}.title cat{$i}_title,cat{$i}.slug cat{$i}_slug,cat{$i}.public cat{$i}_public,cat{$i}.level cat{$i}_level";
        }

        $sql .= ',' . $authorQuery['column_list'];
        $sql .= ' FROM ' . DB::table('article') . ' a';

        for ($i = 1; $i <= 3; ++$i) {
            $sql .= ' LEFT JOIN ' . DB::table('page') . " cat{$i} ON(a.home{$i}=cat{$i}.id)";
        }

        $sql .= ' ' . $authorQuery['joins'];
        $sql .= ' WHERE a.slug=' . DB::val($slug);

        if ($mainCategoryId !== null) {
            $sql .= ' AND a.home1=' . DB::val($mainCategoryId);
        }

        $sql .= ' LIMIT 1';

        $query = DB::queryRow($sql);

        if ($query !== false) {
            $query['author_query'] = $authorQuery;
        }

        return $query;
    }

    /**
     * Create SQL parts for article list query
     *
     * Join aliases: cat1, cat2, cat3
     *
     * @param string $alias alias of the article table
     * @param array $categories ID of article categories (can be empty)
     * @param string|null $sqlConditions custom WHERE conditions
     * @param bool $doCount return a number of matching articles as well 1/0
     * @param bool $checkPublic skip non-public articles if user is not logged in 1/0
     * @param bool $hideInvisible skip invisible articles 1/0
     * @return array joins, where condition, [number of articles]
     */
    static function createFilter(string $alias, array $categories = [], ?string $sqlConditions = null, bool $doCount = false, bool $checkPublic = true, bool $hideInvisible = true): array
    {
        // categories
        if (!empty($categories)) {
            $conditions[] = self::createCategoryFilter($categories);
        }

        // publication time and confirmation
        $conditions[] = "{$alias}.time<=" . time();
        $conditions[] = "{$alias}.confirmed=1";

        // visibility
        if ($hideInvisible) {
            $conditions[] = "{$alias}.visible=1";
        }

        // public status, level
        if ($checkPublic && !User::isLoggedIn()) {
            $conditions[] = "{$alias}.public=1";
            $conditions[] = '(cat1.public=1 OR cat2.public=1 OR cat3.public=1)';
        }

        $conditions[] = '(cat1.level<=' . User::getLevel() . ' OR cat2.level<=' . User::getLevel() . ' OR cat3.level<=' . User::getLevel() . ')';

        // custom conditions
        if (!empty($sqlConditions)) {
            $conditions[] = $sqlConditions;
        }

        // joins
        $joins = '';

        for ($i = 1; $i <= 3; ++$i) {
            if ($i > 1) {
                $joins .= ' ';
            }

            $joins .= 'LEFT JOIN ' . DB::table('page') . " cat{$i} ON({$alias}.home{$i}!=-1 AND cat{$i}.id={$alias}.home{$i})";
        }

        // event
        Extend::call('article.filter', [
            'categories' => $categories,
            'joins' => &$joins,
            'conditions' => &$conditions,
        ]);

        // compose result
        $conditions = implode(' AND ', $conditions);
        $result = [$joins, $conditions];

        // add count
        if ($doCount) {
            $result[] = (int) DB::result(DB::query("SELECT COUNT({$alias}.id) FROM " . DB::table('article') . " {$alias} {$joins} WHERE {$conditions}"));
        }

        return $result;
    }

    /**
     * Create SQL condition for article category filtering
     *
     * @param array $categories category ID list
     * @param string|null $alias alias of the article table, if any
     */
    static function createCategoryFilter(array $categories, ?string $alias = null): string
    {
        if (empty($categories)) {
            return '1';
        }

        if ($alias !== null) {
            $alias .= '.';
        }

        $cond = '(';
        $idList = DB::arr($categories);

        for ($i = 1; $i <= 3; ++$i) {
            if ($i > 1) {
                $cond .= ' OR ';
            }

            $cond .= "{$alias}home{$i} IN({$idList})";
        }

        $cond .= ')';

        return $cond;
    }

    /**
     * Render article preview
     *
     * Article data:
     * - id, title, slug, author, perex, picture_uid, time, comments, public, view_count
     * - cat_slug (slug of main category)
     * - author data from {@see User::createQuery()}
     * - comment_count (optional)
     *
     * @param array $art article data
     * @param array $userQuery output of {@see User::createQuery()}
     * @param bool $info show article info 1/0
     * @param bool $perex show perex 1/0
     */
    static function renderPreview(array $art, array $userQuery, bool $info = true, bool $perex = true): ?string
    {
        // extend
        $extendOutput = Extend::buffer('article.preview', [
            'art' => $art,
            'user_query' => $userQuery,
            'info' => $info,
            'perex' => $perex,
        ]);

        if ($extendOutput !== '') {
            return $extendOutput;
        }

        $output = "<div class=\"list-item article-preview\">\n";

        // title
        $link = Router::article($art['id'], $art['slug'], $art['cat_slug']);
        $output .= '<h2 class="list-title"><a href="' . _e($link) . '">' . $art['title'] . "</a></h2>\n";

        // perex and image
        if ($perex) {
            if (isset($art['picture_uid'])) {
                $thumbnail = Router::file(self::getThumbnail($art['picture_uid']));
            } else {
                $thumbnail = Extend::fetch('article.preview.fallback_thumbnail', ['article' => $art]);
            }

            $output .= '<div class="list-perex">'
                . ($thumbnail !== null ? '<a href="' . _e($link) . '"><img class="list-perex-image" src="' . _e($thumbnail) . '" alt="' . $art['title'] . '"></a>' : '')
                . $art['perex']
                . "</div>\n";
        }

        // info
        if ($info) {
            $infos = [
                'author' => [_lang('article.author'), Router::userFromQuery($userQuery, $art)],
                'posted' => [_lang('article.posted'), GenericTemplates::renderDate($art['time'], 'article')],
                'view_count' => [_lang('article.view_count'), _num($art['view_count']) . 'x'],
            ];

            if ($art['comments'] && isset($art['comment_count']) && Settings::get('comments')) {
                $infos['comments'] = [_lang('article.comments'), _num($art['comment_count'])];
            }

            Extend::call('article.preview.infos', [
                'art' => $art,
                'user_query' => $userQuery,
                'perex' => $perex,
                'infos' => &$infos,
            ]);

            $output .= GenericTemplates::renderInfos($infos);
        } elseif ($perex && isset($art['picture_uid'])) {
            $output .= "<div class=\"cleaner\"></div>\n";
        }

        $output .= "</div>\n";

        return $output;
    }

    /**
     * Upload a new article image
     *
     * Returns image UID or NULL on failure.
     */
    static function uploadImage(
        string $source,
        string $originalFilename,
        ?ImageException &$exception = null
    ): ?string {
        $uid = StringGenerator::generateUniqueHash();

        return ImageService::process(
            'article',
            $source,
            self::getImagePath($uid),
            [
                'resize' => [
                    'mode' => ImageTransformer::RESIZE_FIT,
                    'keep_smaller' => true,
                    'w' => Settings::get('article_pic_w'),
                    'h' => Settings::get('article_pic_h'),
                ],
                'format' => ImageLoader::getFormat($originalFilename),
            ],
            $exception
        )
            ? $uid
            : null;
    }

    /**
     * Get article image path
     */
    static function getImagePath(string $imageUid): string
    {
        return ImageStorage::getPath('images/articles/', $imageUid, 'jpg', 1);
    }

    /**
     * Get article image thumbnail
     */
    static function getThumbnail(string $imageUid): string
    {
        return ImageService::getThumbnail(
            'article',
            self::getImagePath($imageUid),
            [
                'mode' => ImageTransformer::RESIZE_FIT,
                'w' => Settings::get('article_pic_thumb_w'),
                'h' => Settings::get('article_pic_thumb_h'),
            ]
        );
    }

    /**
     * Remove article image
     */
    static function removeImage(string $imageUid): bool
    {
        return @unlink(self::getImagePath($imageUid));
    }
}
