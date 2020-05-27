<?php

namespace Sunlight\Comment;

use Sunlight\Bbcode;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Template;

abstract class Comment
{
    /**
     * Vyhodnotit pravo uzivatele na pristup k prispevku
     *
     * @param array $userQuery vystup z {@see User::createQuery()}
     * @param array $post      data prispevku (potreba data uzivatele a post.time)
     * @return bool
     */
    static function checkAccess(array $userQuery, array $post)
    {
        // uzivatel je prihlasen
        if (_logged_in) {
            // extend
            $access = Extend::fetch('posts.access', array(
                'post' => $post,
                'user_query' => $userQuery,
            ));
            if ($access !== null) {
                return $access;
            }

            // je uzivatel autorem prispevku?
            if ($post[$userQuery['prefix'] . 'id'] == _user_id && ($post['time'] + _postadmintime > time() || _priv_unlimitedpostaccess)) {
                return true;
            } elseif (_priv_adminposts && _priv_level > $post[$userQuery['prefix'] . 'group_level']) {
                // uzivatel ma pravo spravovat cizi prispevky
                return true;
            }
        }

        return false;
    }

    /**
     * Sestavit casti SQL dotazu pro vypis komentaru
     *
     * Join aliasy: home_page, home_art, home_cat1..3, home_post
     * Sloupce: data postu + (page|cat|art)_(title|slug), xhome_subject
     *
     * @param string $alias         alias tabulky komentaru pouzity v dotazu
     * @param array  $types         pole s typy prispevku, ktere maji byt nacteny
     * @param array  $homes         pole s ID domovskych polozek
     * @param string $sqlConditions SQL s vlastnimi WHERE podminkami
     * @param bool   $doCount       vracet take pocet odpovidajicich prispevku 1/0
     * @return array sloupce, joiny, where podminka, [pocet]
     */
    static function createFilter($alias, array $types = array(), array $homes = array(), $sqlConditions = null, $doCount = false)
    {
        // sloupce
        $columns = "{$alias}.id,{$alias}.type,{$alias}.home,{$alias}.xhome,{$alias}.subject,
{$alias}.author,{$alias}.guest,{$alias}.time,{$alias}.text,{$alias}.flag,
home_page.title page_title,home_page.slug page_slug,
home_cat1.title cat_title,home_cat1.slug cat_slug,
home_art.title art_title,home_art.slug art_slug,
home_post.subject xhome_subject";

        // podminky
        $conditions = array();

        if (!empty($types)) {
            $conditions[] = "{$alias}.type IN(" . DB::arr($types) . ")";
        }
        if (!empty($homes)) {
            $conditions[] = "{$alias}.home IN(" . DB::arr($homes) . ")";
        }

        $conditions[] = "(home_page.id IS NULL OR " . (_logged_in ? '' : 'home_page.public=1 AND ') . "home_page.level<=" . _priv_level . ")
AND (home_art.id IS NULL OR " . (_logged_in ? '' : 'home_art.public=1 AND ') . "home_art.time<=" . time() . " AND home_art.confirmed=1)
AND ({$alias}.type!=" . _post_article_comment . " OR (
    " . (_logged_in ? '' : '(home_cat1.public=1 OR home_cat2.public=1 OR home_cat3.public=1) AND') . "
    (home_cat1.level<=" . _priv_level . " OR home_cat2.level<=" . _priv_level . " OR home_cat3.level<=" . _priv_level . ")
))";

        // vlastni podminky
        if (!empty($sqlConditions)) {
            $conditions[] = $sqlConditions;
        }

        // joiny
        $joins = "LEFT JOIN " . _page_table . " home_page ON({$alias}.type IN(1,3,5) AND {$alias}.home=home_page.id)
LEFT JOIN " . _article_table . " home_art ON({$alias}.type=" . _post_article_comment . " AND {$alias}.home=home_art.id)
LEFT JOIN " . _page_table . " home_cat1 ON({$alias}.type=" . _post_article_comment . " AND home_art.home1=home_cat1.id)
LEFT JOIN " . _page_table . " home_cat2 ON({$alias}.type=" . _post_article_comment . " AND home_art.home2!=-1 AND home_art.home2=home_cat2.id)
LEFT JOIN " . _page_table . " home_cat3 ON({$alias}.type=" . _post_article_comment . " AND home_art.home3!=-1 AND home_art.home3=home_cat3.id)
LEFT JOIN " . _comment_table . " home_post ON({$alias}.type=" . _post_forum_topic . " AND {$alias}.xhome!=-1 AND {$alias}.xhome=home_post.id)";

        // extend
        Extend::call('posts.filter', array(
            'columns' => &$columns,
            'joins' => &$joins,
            'conditions' => &$conditions,
            'alias' => $alias,
        ));

        // sestaveni vysledku
        $result = array(
            $columns,
            $joins,
            implode(' AND ', $conditions),
        );

        // pridat pocet
        if ($doCount) {
            $result[] = (int) DB::result(DB::query("SELECT COUNT({$alias}.id) FROM " . _comment_table . " {$alias} {$joins} WHERE {$result[2]}"), 0);
        }

        return $result;
    }

    /**
     * Vykreslit text prispevku
     *
     * @param string $input   vstupni text (HTML)
     * @param bool   $smileys vyhodnotit smajliky 1/0
     * @param bool   $bbcode  vyhodnotit bbcode 1/0
     * @param bool   $nl2br   prevest odrakovani na <br>
     * @return string
     */
    static function render($input, $smileys = true, $bbcode = true, $nl2br = true)
    {
        // event
        Extend::call('post.parse', array(
            'content' => &$input,
            'smileys' => $smileys,
            'bbcode' => $bbcode,
            'nl2br' => $nl2br,
        ));

        // vyhodnoceni smajlu
        if (_smileys && $smileys) {
            $template = Template::getCurrent();

            $input = preg_replace('{\*(\d{1,3})\*}s', '<img src=\'' . $template->getWebPath() . '/images/smileys/$1.' . $template->getOption('smiley.format') . '\' alt=\'$1\' class=\'post-smiley\'>', $input, 32);
        }

        // vyhodnoceni BBCode
        if (_bbcode && $bbcode) {
            $input = Bbcode::parse($input);
        }

        // prevedeni novych radku
        if ($nl2br) {
            $input = nl2br($input, false);
        }

        // navrat vystupu
        return $input;
    }
}
