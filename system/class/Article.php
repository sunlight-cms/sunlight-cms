<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;

abstract class Article
{
    /**
     * Vyhodnotit pravo aktualniho uzivatele k pristupu ke clanku
     *
     * @param array $article          pole s daty clanku (potreba id,time,confirmed,author,public,home1,home2,home3)
     * @param bool  $check_categories kontrolovat kategorie 1/0
     * @return bool
     */
    static function checkAccess($article, $check_categories = true)
    {
        // nevydany / neschvaleny clanek
        if (!$article['confirmed'] || $article['time'] > time()) {
            return _priv_adminconfirm || $article['author'] == _user_id;
        }

        // pristup k clanku
        if (!User::checkPublicAccess($article['public'])) {
            return false;
        }

        // pristup ke kategoriim
        if ($check_categories) {
            // nacist
            $homes = array($article['home1']);
            if ($article['home2'] != -1) {
                $homes[] = $article['home2'];
            }
            if ($article['home3'] != -1) {
                $homes[] = $article['home3'];
            }
            $result = DB::query('SELECT public,level FROM ' . _root_table . ' WHERE id IN(' . implode(',', $homes) . ')');
            while ($r = DB::row($result)) {
                if (User::checkPublicAccess($r['public'], $r['level'])) {
                    // do kategorie je pristup (staci alespon 1)
                    return true;
                }
            }

            // neni pristup k zadne kategorii
            return false;
        } else {
            // nekontrolovat
            return true;
        }
    }

    /**
     * Nalezt clanek a nacist jeho data
     * Jsou nactena vsechna data clanku + cat[1|2|3]_[id|title|slug|public|level] a author_query
     *
     * @param string   $slug   identifikator clanku
     * @param int|null $cat_id ID hlavni kategorie clanku (home1)
     * @return array|bool false pri nenalezeni
     */
    static function find($slug, $cat_id = null)
    {
        $author_user_query = User::createQuery('a.author');

        $sql = 'SELECT a.*';
        for ($i = 1; $i <= 3; ++$i) {
            $sql .= ",cat{$i}.id cat{$i}_id,cat{$i}.title cat{$i}_title,cat{$i}.slug cat{$i}_slug,cat{$i}.public cat{$i}_public,cat{$i}.level cat{$i}_level";
        }
        $sql .= ',' . $author_user_query['column_list'];
        $sql .= ' FROM ' . _articles_table . ' a';
        for ($i = 1; $i <= 3; ++$i) {
            $sql .= ' LEFT JOIN ' . _root_table . " cat{$i} ON(a.home{$i}=cat{$i}.id)";
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
     * @param string $alias         alias tabulky clanku pouzity v dotazu
     * @param array  $categories    pole s ID kategorii, muze byt prazdne
     * @param string $sqlConditions SQL s vlastnimi WHERE podminkami
     * @param bool   $doCount       vracet take pocet odpovidajicich clanku 1/0
     * @param bool   $checkPublic   nevypisovat neverejne clanky, neni-li uzivatel prihlasen
     * @param bool   $hideInvisible nevypisovat neviditelne clanky
     * @return array joiny, where podminka, [pocet clanku]
     */
    static function createFilter($alias, array $categories = array(), $sqlConditions = null, $doCount = false, $checkPublic = true, $hideInvisible = true)
    {
        //kategorie
        if (!empty($categories)) {
            $conditions[] = static::createCategoryFilter($categories);
        }

        // cas vydani
        $conditions[] = "{$alias}.time<=" . time();
        $conditions[] = "{$alias}.confirmed=1";

        // neviditelnost
        if ($hideInvisible) {
            $conditions[] = "{$alias}.visible=1";
        }

        // neverejnost
        if ($checkPublic && !_logged_in) {
            $conditions[] = "{$alias}.public=1";
            $conditions[] = "(cat1.public=1 OR cat2.public=1 OR cat3.public=1)";
        }
        $conditions[] = "(cat1.level<=" . _priv_level . " OR cat2.level<=" . _priv_level . " OR cat3.level<=" . _priv_level . ")";

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
            $joins .= 'LEFT JOIN ' . _root_table . " cat{$i} ON({$alias}.home{$i}!=-1 AND cat{$i}.id={$alias}.home{$i})";
        }

        // spojit podminky
        $conditions = implode(' AND ', $conditions);

        // sestaveni vysledku
        $result = array($joins, $conditions);

        // pridat pocet
        if ($doCount) {
            $result[] = (int) DB::result(DB::query("SELECT COUNT({$alias}.id) FROM " . _articles_table . " {$alias} {$joins} WHERE {$conditions}"), 0);
        }

        return $result;
    }

    /**
     * Sestaveni casti SQL dotazu po WHERE pro vyhledani clanku v urcitych kategoriich.
     *
     * @param array       $categories pole s ID kategorii
     * @param string|null $alias      alias tabulky clanku pouzity v dotazu
     * @return string
     */
    static function createCategoryFilter(array $categories, $alias = null)
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
     * @param array    $art       pole s daty clanku vcetne cat_slug a data uzivatele z {@see _userQuery()}
     * @param array    $userQuery vystup funkce {@see _userQuery()}
     * @param bool     $info      vypisovat radek s informacemi 1/0
     * @param bool     $perex     vypisovat perex 1/0
     * @param int|null pocet      komentaru (null = nezobrazi se)
     * @return string
     */
    static function renderPreview(array $art, array $userQuery, $info = true, $perex = true, $comment_count = null)
    {
        // extend
        $extendOutput = Extend::buffer('article.preview', array(
            'art' => $art,
            'user_query' => $userQuery,
            'info' => $info,
            'perex' => $perex,
            'comment_count' => $comment_count,
        ));
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
                $thumbnail = Picture::getThumbnail(
                    Picture::get(_root . 'images/articles/', null, $art['picture_uid'], 'jpg'),
                    array(
                        'mode' => 'fit',
                        'x' => _article_pic_thumb_w,
                        'y' => _article_pic_thumb_h,
                    )
                );
            } else {
                $thumbnail = null;
            }

            $output .= "<div class='list-perex'>" . ($thumbnail !== null ? "<a href='" . _e($link) . "'><img class='list-perex-image' src='" . _e(Router::file($thumbnail)) . "' alt='" . $art['title'] . "'></a>" : '') . $art['perex'] . "</div>\n";
        }

        // info
        if ($info == true) {

            $infos = array(
                'author' => array(_lang('article.author'), Router::userFromQuery($userQuery, $art)),
                'posted' => array(_lang('article.posted'), Generic::renderTime($art['time'], 'article')),
                'readnum' => array(_lang('article.readnum'), $art['readnum'] . 'x'),
            );

            if ($art['comments'] == 1 && _comments && $comment_count !== null) {
                $infos['comments'] = array(_lang('article.comments'), $comment_count);
            }

            Extend::call('article.preview.infos', array(
                'art' => $art,
                'user_query' => $userQuery,
                'perex' => $perex,
                'comment_count' => $comment_count,
                'infos' => &$infos,
            ));

            $output .= Generic::renderInfos($infos);
        } elseif ($perex && isset($art['picture_uid'])) {
            $output .= "<div class='cleaner'></div>\n";
        }

        $output .= "</div>\n";

        return $output;
    }
}
