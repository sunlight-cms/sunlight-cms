<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Image\ImageException;
use Sunlight\Image\ImageLoader;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageStorage;
use Sunlight\Image\ImageTransformer;
use Sunlight\Util\StringGenerator;

abstract class Article
{
    /**
     * Vyhodnotit pravo aktualniho uzivatele k pristupu ke clanku
     *
     * @param array $article pole s daty clanku (potreba id,time,confirmed,author,public,home1,home2,home3)
     * @param bool $check_categories kontrolovat kategorie 1/0
     */
    static function checkAccess(array $article, bool $check_categories = true): bool
    {
        // nevydany / neschvaleny clanek
        if (!$article['confirmed'] || $article['time'] > time()) {
            return User::hasPrivilege('adminconfirm') || User::equals($article['author']);
        }

        // pristup k clanku
        if (!User::checkPublicAccess($article['public'])) {
            return false;
        }

        // pristup ke kategoriim
        if ($check_categories) {
            // nacist
            $homes = [$article['home1']];
            if ($article['home2'] != -1) {
                $homes[] = $article['home2'];
            }
            if ($article['home3'] != -1) {
                $homes[] = $article['home3'];
            }
            $result = DB::query('SELECT public,level FROM ' . DB::table('page') . ' WHERE id IN(' . implode(',', $homes) . ')');
            while ($r = DB::row($result)) {
                if (User::checkPublicAccess($r['public'], $r['level'])) {
                    // do kategorie je pristup (staci alespon 1)
                    return true;
                }
            }

            // neni pristup k zadne kategorii
            return false;
        }

        // nekontrolovat
        return true;
    }

    /**
     * Nalezt clanek a nacist jeho data
     * Jsou nactena vsechna data clanku + cat[1|2|3]_[id|title|slug|public|level] a author_query
     *
     * @param string $slug identifikator clanku
     * @param int|null $cat_id ID hlavni kategorie clanku (home1)
     * @return array|bool false pri nenalezeni
     */
    static function find(string $slug, ?int $cat_id = null)
    {
        $author_user_query = User::createQuery('a.author');

        $sql = 'SELECT a.*';
        for ($i = 1; $i <= 3; ++$i) {
            $sql .= ",cat{$i}.id cat{$i}_id,cat{$i}.title cat{$i}_title,cat{$i}.slug cat{$i}_slug,cat{$i}.public cat{$i}_public,cat{$i}.level cat{$i}_level";
        }
        $sql .= ',' . $author_user_query['column_list'];
        $sql .= ' FROM ' . DB::table('article') . ' a';
        for ($i = 1; $i <= 3; ++$i) {
            $sql .= ' LEFT JOIN ' . DB::table('page') . " cat{$i} ON(a.home{$i}=cat{$i}.id)";
        }
        $sql .= ' ' . $author_user_query['joins'];
        $sql .= ' WHERE a.slug=' . DB::val($slug);
        if ($cat_id !== null) {
            $sql .= ' AND a.home1=' . DB::val($cat_id);
        }
        $sql .= ' LIMIT 1';

        $query = DB::queryRow($sql);
        if ($query !== false) {
            $query['author_query'] = $author_user_query;
        }

        return $query;
    }

    /**
     * Sestavit casti SQL dotazu pro vypis clanku
     *
     * Join aliasy: cat1, cat2, cat3
     *
     * @param string $alias alias tabulky clanku pouzity v dotazu
     * @param array $categories pole s ID kategorii, muze byt prazdne
     * @param string|null $sqlConditions SQL s vlastnimi WHERE podminkami
     * @param bool $doCount vracet take pocet odpovidajicich clanku 1/0
     * @param bool $checkPublic nevypisovat neverejne clanky, neni-li uzivatel prihlasen
     * @param bool $hideInvisible nevypisovat neviditelne clanky
     * @return array joiny, where podminka, [pocet clanku]
     */
    static function createFilter(string $alias, array $categories = [], ?string $sqlConditions = null, bool $doCount = false, bool $checkPublic = true, bool $hideInvisible = true): array
    {
        //kategorie
        if (!empty($categories)) {
            $conditions[] = self::createCategoryFilter($categories);
        }

        // cas vydani
        $conditions[] = "{$alias}.time<=" . time();
        $conditions[] = "{$alias}.confirmed=1";

        // neviditelnost
        if ($hideInvisible) {
            $conditions[] = "{$alias}.visible=1";
        }

        // neverejnost
        if ($checkPublic && !User::isLoggedIn()) {
            $conditions[] = "{$alias}.public=1";
            $conditions[] = "(cat1.public=1 OR cat2.public=1 OR cat3.public=1)";
        }
        $conditions[] = "(cat1.level<=" . User::getLevel() . " OR cat2.level<=" . User::getLevel() . " OR cat3.level<=" . User::getLevel() . ")";

        // vlastni podminky
        if (!empty($sqlConditions)) {
            $conditions[] = $sqlConditions;
        }

        // joiny
        $joins = '';
        for ($i = 1; $i <= 3; ++$i) {
            if ($i > 1) {
                $joins .= ' ';
            }
            $joins .= 'LEFT JOIN ' . DB::table('page') . " cat{$i} ON({$alias}.home{$i}!=-1 AND cat{$i}.id={$alias}.home{$i})";
        }

        // spojit podminky
        $conditions = implode(' AND ', $conditions);

        // sestaveni vysledku
        $result = [$joins, $conditions];

        // pridat pocet
        if ($doCount) {
            $result[] = (int) DB::result(DB::query("SELECT COUNT({$alias}.id) FROM " . DB::table('article') . " {$alias} {$joins} WHERE {$conditions}"));
        }

        return $result;
    }

    /**
     * Sestaveni casti SQL dotazu po WHERE pro vyhledani clanku v urcitych kategoriich.
     *
     * @param array $categories pole s ID kategorii
     * @param string|null $alias alias tabulky clanku pouzity v dotazu
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
     * Vytvoreni nahledu clanku pro vypis
     *
     * @param array $art pole s daty clanku vcetne cat_slug a data uzivatele z {@see User::createQuery()}
     * @param array $userQuery vystup funkce {@see User::createQuery()}
     * @param bool $info vypisovat radek s informacemi 1/0
     * @param bool $perex vypisovat perex 1/0
     * @param int|null $comment_count pocet komentaru (null = nezobrazi se)
     */
    static function renderPreview(array $art, array $userQuery, bool $info = true, bool $perex = true, ?int $comment_count = null): ?string
    {
        // extend
        $extendOutput = Extend::buffer('article.preview', [
            'art' => $art,
            'user_query' => $userQuery,
            'info' => $info,
            'perex' => $perex,
            'comment_count' => $comment_count,
        ]);
        if ($extendOutput !== '') {
            return $extendOutput;
        }

        $output = "<div class='list-item article-preview'>\n";

        // titulek
        $link = Router::article($art['id'], $art['slug'], $art['cat_slug']);
        $output .= "<h2 class='list-title'><a href='" . $link . "'>" . $art['title'] . "</a></h2>\n";

        // perex a obrazek
        if ($perex == true) {
            if (isset($art['picture_uid'])) {
                $thumbnail = self::getThumbnail($art['picture_uid']);
            } else {
                $thumbnail = null;
            }

            $output .= "<div class='list-perex'>"
                . ($thumbnail !== null ? "<a href='" . _e($link) . "'><img class='list-perex-image' src='" . _e(Router::file($thumbnail)) . "' alt='" . $art['title'] . "'></a>" : '')
                . $art['perex']
                . "</div>\n";
        }

        // info
        if ($info == true) {
            $infos = [
                'author' => [_lang('article.author'), Router::userFromQuery($userQuery, $art)],
                'posted' => [_lang('article.posted'), GenericTemplates::renderTime($art['time'], 'article')],
                'readnum' => [_lang('article.readnum'), $art['readnum'] . 'x'],
            ];

            if ($art['comments'] == 1 && Settings::get('comments') && $comment_count !== null) {
                $infos['comments'] = [_lang('article.comments'), $comment_count];
            }

            Extend::call('article.preview.infos', [
                'art' => $art,
                'user_query' => $userQuery,
                'perex' => $perex,
                'comment_count' => $comment_count,
                'infos' => &$infos,
            ]);

            $output .= GenericTemplates::renderInfos($infos);
        } elseif ($perex && isset($art['picture_uid'])) {
            $output .= "<div class='cleaner'></div>\n";
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
