<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Request;

class Paginator
{
    /**
     * Strankovani vysledku
     *
     * Format vystupu:
     * array(
     *      paging      => html kod seznamu stran
     *      sql_limit   => cast sql dotazu - limit
     *      current     => aktualni strana (1+)
     *      total       => celkovy pocet stran
     *      count       => pocet polozek
     *      first       => cislo prvni zobrazene polozky
     *      last        => cislo posledni zobrazene polozky
     *      per_page    => pocet polozek na jednu stranu
     * )
     *
     * @param string      $url        vychozi adresa (cista - bez HTML entit!)
     * @param int         $limit      limit polozek na 1 stranu
     * @param string|int  $table      nazev tabulky (tabulka[:alias]) nebo celkovy pocet polozek jako integer
     * @param string      $conditions kod SQL dotazu za WHERE v SQL dotazu pro zjistovani poctu polozek; pokud je $table cislo, nema tato promenna zadny vyznam
     * @param string      $linksuffix retezec pridavany za kazdy odkaz generovany strankovanim
     * @param string|null $param      nazev parametru pro cislo strany (null = 'page')
     * @param bool        $autolast   posledni strana je vychozi strana 1/0
     * @return array
     */
    static function render($url, $limit, $table, $conditions = '1', $linksuffix = '', $param = null, $autolast = false)
    {
        // alias tabulky
        if (is_string($table)) {
            $table = explode(':', $table);
            $alias = (isset($table[1]) ? $table[1] : null);
            $table = $table[0];
        } else {
            $alias = null;
        }

        // priprava promennych
        if (!isset($param)) {
            $param = 'page';
        }
        if (is_string($table)) {
            $count = DB::result(DB::query("SELECT COUNT(*) FROM " . DB::escIdt($table) . (isset($alias) ? " AS {$alias}" : '') . " WHERE " . $conditions), 0);
        } else {
            $count = $table;
        }

        $pages = max(1, ceil($count / $limit));
        if (isset($_GET[$param])) {
            $s = abs((int) Request::get($param) - 1);
        } elseif ($autolast) {
            $s = $pages - 1;
        } else {
            $s = 0;
        }

        if ($s + 1 > $pages) {
            $s = $pages - 1;
        }
        $start = $s * $limit;
        $beginpage = $s + 1 - _showpages;
        if ($beginpage < 1) {
            $endbonus = abs($beginpage) + 1;
            $beginpage = 1;
        } else {
            $endbonus = 0;
        }
        $endpage = $s + 1 + _showpages + $endbonus;
        if ($endpage > $pages) {
            $beginpage -= $endpage - $pages;
            if ($beginpage < 1) {
                $beginpage = 1;
            }
            $endpage = $pages;
        }

        // vypis stran
        $paging = null;
        Extend::call('paging.render', [
            'url' => $url,
            'param' => $param,
            'autolast' => $autolast,
            'table' => $table,
            'count' => $count,
            'offset' => $start,
            'limit' => $limit,
            'current' => $s + 1,
            'total' => $pages,
            'begin' => $beginpage,
            'end' => $endpage,
            'paging' => &$paging,
        ]);

        if ($paging === null) {
            if ($pages > 1) {

                if (strpos($url, "?") === false) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }

                $url = _e($url);

                $paging = "\n<div class='paging'>\n<span class='paging-label'>" . _lang('global.paging') . ":</span>\n";

                // prvni
                if ($beginpage > 1) {
                    $paging .= "<a href='" . $url . $param . "=1" . $linksuffix . "' title='" . _lang('global.first') . "'>1</a><span class='paging-first-addon'> ...</span>\n";
                }

                // predchozi
                if ($s + 1 != 1) {
                    $paging .= "<a class='paging-prev' href='" . $url . $param . "=" . ($s) . $linksuffix . "'>&laquo; " . _lang('global.previous') . "</a>\n";
                }

                // strany
                $paging .= "<span class='paging-pages'>\n";
                for ($x = $beginpage; $x <= $endpage; ++$x) {
                    if ($x == $s + 1) {
                        $class = " class='act'";
                    } else {
                        $class = "";
                    }
                    $paging .= "<a href='" . $url . $param . "=" . $x . $linksuffix . "'" . $class . ">" . $x . "</a>\n";
                    if ($x != $endpage) {
                        $paging .= " ";
                    }
                }
                $paging .= "</span>\n";

                // dalsi
                if ($s + 1 != $pages) {
                    $paging .= "<a class='paging-next' href='" . $url . $param . "=" . ($s + 2) . $linksuffix . "'>" . _lang('global.next') . " &raquo;</a>\n";
                }
                if ($endpage < $pages) {
                    $paging .= "<span class='paging-last-addon'> ... </span><a class='paging-last' href='" . $url . $param . "=" . $pages . $linksuffix . "' title='" . _lang('global.last') . "'>" . $pages . "</a>\n";
                }

                $paging .= "\n</div>\n\n";
            } else {
                $paging = '';
            }
        }

        // return
        $end_item = ($start + $limit - 1);

        return [
            'paging' => $paging,
            'sql_limit' => 'LIMIT ' . $start . ', ' . $limit,
            'current' => ($s + 1),
            'total' => $pages,
            'count' => $count,
            'first' => $start,
            'last' => (($end_item > $count - 1) ? $count - 1 : $end_item),
            'per_page' => $limit,
        ];
    }

    /**
     * Zjistit stranku, na ktere se polozka nachazi pri danem strankovani a podmince razeni
     *
     * @param int    $limit      pocet polozek na jednu stranu
     * @param string $table      nazev tabulky v databazi
     * @param string $conditions kod SQL dotazu za WHERE v SQL dotazu pro zjistovani poctu polozek
     * @return int
     */
    static function getItemPage($limit, $table, $conditions = "1")
    {
        $count = DB::result(DB::query("SELECT COUNT(*) FROM " .  DB::escIdt($table) . " WHERE " . $conditions), 0);

        return floor($count / $limit + 1);
    }

    /**
     * Zjisteni, zda je polozka s urcitym cislem v rozsahu aktualni strany strankovani
     *
     * @param array $pagingdata pole, ktere vraci funkce {@see Paginator::render()}
     * @param int   $itemnumber poradove cislo polozky (poradi zacina nulou)
     * @return bool
     */
    static function isItemInRange($pagingdata, $itemnumber)
    {
        return $itemnumber >= $pagingdata['first'] && $itemnumber <= $pagingdata['last'];
    }

    /**
     * Zjisteni, zda-li ma byt strankovani zobrazeno nahore
     *
     * @return bool
     */
    static function atTop()
    {
        return _pagingmode == 1 || _pagingmode == 2;
    }

    /**
     * Zjisteni, zda-li ma byt strankovani zobrazeno dole
     *
     * @return bool
     */
    static function atBottom()
    {
        return _pagingmode == 2 || _pagingmode == 3;
    }
}
