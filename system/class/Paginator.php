<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Request;

class Paginator
{
    /**
     * Render a paginator
     *
     * @param string $url base URL to add page parameter to
     * @param int $limit max item per page
     * @param string|int $tableOrCount table name (table[:alias]) or an already known total number of items
     * @param string $conditions SQL condition used to filter items from table
     * @param string $linksuffix content to append after every paginator URL
     * @param string|null $param query parameter name (defaults to 'page')
     * @param bool $autolast last page is the default page 1/0
     * @return array{
     *      paging: string,
     *      sql_limit: string,
     *      current: int,
     *      total: int,
     *      count: int,
     *      first: int,
     *      last: int,
     *      per_page: int,
     * }
     */
    static function render(
        string $url,
        int $limit,
        $tableOrCount,
        string $conditions = '1',
        string $linksuffix = '',
        ?string $param = null,
        bool $autolast = false
    ): array {
        // table alias
        if (is_string($tableOrCount)) {
            $tableOrCount = explode(':', $tableOrCount);
            $alias = ($tableOrCount[1] ?? null);
            $tableOrCount = $tableOrCount[0];
        } else {
            $alias = null;
        }

        // prepare variables
        if (!isset($param)) {
            $param = 'page';
        }
        if (is_string($tableOrCount)) {
            $count = DB::result(DB::query('SELECT COUNT(*) FROM ' . DB::escIdt($tableOrCount) . (isset($alias) ? " AS {$alias}" : '') . ' WHERE ' . $conditions));
        } else {
            $count = $tableOrCount;
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
        $beginpage = $s + 1 - Settings::get('showpages');
        if ($beginpage < 1) {
            $endbonus = abs($beginpage) + 1;
            $beginpage = 1;
        } else {
            $endbonus = 0;
        }
        $endpage = $s + 1 + Settings::get('showpages') + $endbonus;
        if ($endpage > $pages) {
            $beginpage -= $endpage - $pages;
            if ($beginpage < 1) {
                $beginpage = 1;
            }
            $endpage = $pages;
        }

        // render pages
        $paging = null;
        Extend::call('paging.render', [
            'url' => $url,
            'param' => $param,
            'autolast' => $autolast,
            'table' => $tableOrCount,
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
                $linksuffix = _e($linksuffix);

                if (strpos($url, '?') === false) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }

                $url = _e($url);

                $paging = "\n<div class=\"paging\">\n<span class=\"paging-label\">" . _lang('global.paging') . ":</span>\n";

                // first
                if ($beginpage > 1) {
                    $paging .= '<a href="' . $url . $param . '=1' . $linksuffix . '" title="' . _lang('global.first') . "\">1</a><span class=\"paging-first-addon\"> ...</span>\n";
                }

                // previous
                if ($s + 1 != 1) {
                    $paging .= '<a class="paging-prev" href="' . $url . $param . '=' . ($s) . $linksuffix . '">&laquo; ' . _lang('global.previous') . "</a>\n";
                }

                // pages
                $paging .= "<span class=\"paging-pages\">\n";
                for ($x = $beginpage; $x <= $endpage; ++$x) {
                    if ($x == $s + 1) {
                        $class = ' class="act"';
                    } else {
                        $class = '';
                    }
                    $paging .= '<a href="' . $url . $param . '=' . $x . $linksuffix . '"' . $class . '>' . $x . "</a>\n";
                    if ($x != $endpage) {
                        $paging .= ' ';
                    }
                }
                $paging .= "</span>\n";

                // next
                if ($s + 1 != $pages) {
                    $paging .= '<a class="paging-next" href="' . $url . $param . '=' . ($s + 2) . $linksuffix . '">' . _lang('global.next') . " &raquo;</a>\n";
                }

                // last
                if ($endpage < $pages) {
                    $paging .= '<span class="paging-last-addon"> ... </span><a class="paging-last" href="' . $url . $param . '=' . $pages . $linksuffix . '" title="' . _lang('global.last') . '">' . $pages . "</a>\n";
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
     * Determine item page
     *
     * @param int $limit max item per page
     * @param string $conditions SQL condition used to filter items from table
     */
    static function getItemPage(int $limit, string $table, string $conditions = '1'): int
    {
        $count = DB::result(DB::query('SELECT COUNT(*) FROM ' .  DB::escIdt($table) . ' WHERE ' . $conditions));

        return (int) floor($count / $limit + 1);
    }

    /**
     * Check if an item is in range of the current page
     *
     * @param array $pagingdata output of {@see Paginator::render()}
     * @param int $itemnumber 0-based item number
     */
    static function isItemInRange(array $pagingdata, int $itemnumber): bool
    {
        return $itemnumber >= $pagingdata['first'] && $itemnumber <= $pagingdata['last'];
    }

    /**
     * See if paginator should be at the top of the page
     */
    static function atTop(): bool
    {
        return Settings::get('pagingmode') == 1 || Settings::get('pagingmode') == 2;
    }

    /**
     * See if paginator should be at the bottom of the page
     */
    static function atBottom(): bool
    {
        return Settings::get('pagingmode') == 2 || Settings::get('pagingmode') == 3;
    }
}
