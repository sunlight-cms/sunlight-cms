<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Request;

class Paginator
{
    /**
     * Render a paginator
     *
     * Supported options:
     * ------------------
     * param ('page')       query parameter name
     * link_suffix ('')     string to append after every link
     * auto_last (0)        default to the last page 1/0
     *
     * @param string $url base URL to add page parameter to
     * @param int $limit max item per page
     * @param int $count total number of items
     * @param array{
     *     param?: string,
     *     link_suffix?: string,
     *     auto_last?: bool,
     * } $options see descripion
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
    static function paginate(
        string $url,
        int $limit,
        int $count,
        array $options = []
    ): array {
        $options += [
            'param' => 'page',
            'link_suffix' => '',
            'auto_last' => false,
        ];

        $pages = max(1, ceil($count / $limit));

        if (isset($_GET[$options['param']])) {
            $current_page = abs((int) Request::get($options['param']) - 1);
        } elseif ($options['auto_last']) {
            $current_page = $pages - 1;
        } else {
            $current_page = 0;
        }

        if ($current_page + 1 > $pages) {
            $current_page = $pages - 1;
        }

        $start = $current_page * $limit;
        $beginpage = $current_page + 1 - Settings::get('showpages');

        if ($beginpage < 1) {
            $endbonus = abs($beginpage) + 1;
            $beginpage = 1;
        } else {
            $endbonus = 0;
        }

        $endpage = $current_page + 1 + Settings::get('showpages') + $endbonus;

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
            'options' => $options,
            'count' => $count,
            'offset' => $start,
            'limit' => $limit,
            'current' => $current_page + 1,
            'total' => $pages,
            'begin' => $beginpage,
            'end' => $endpage,
            'paging' => &$paging,
        ]);

        if ($paging === null) {
            if ($pages > 1) {
                $link_suffix = _e($options['link_suffix']);

                if (strpos($url, '?') === false) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }

                $url = _e($url);

                $paging = "\n<div class=\"paging\">\n<span class=\"paging-label\">" . _lang('global.paging') . ":</span>\n";

                // first
                if ($beginpage > 1) {
                    $paging .= '<a href="' . $url . $options['param'] . '=1' . $link_suffix . '" title="' . _lang('global.first') . "\">1</a><span class=\"paging-first-addon\"> ...</span>\n";
                }

                // previous
                if ($current_page + 1 != 1) {
                    $paging .= '<a class="paging-prev" href="' . $url . $options['param'] . '=' . ($current_page) . $link_suffix . '">&laquo; ' . _lang('global.previous') . "</a>\n";
                }

                // pages
                $paging .= "<span class=\"paging-pages\">\n";

                for ($x = $beginpage; $x <= $endpage; ++$x) {
                    if ($x == $current_page + 1) {
                        $class = ' class="act"';
                    } else {
                        $class = '';
                    }

                    $paging .= '<a href="' . $url . $options['param'] . '=' . $x . $link_suffix . '"' . $class . '>' . $x . "</a>\n";

                    if ($x != $endpage) {
                        $paging .= ' ';
                    }
                }

                $paging .= "</span>\n";

                // next
                if ($current_page + 1 != $pages) {
                    $paging .= '<a class="paging-next" href="' . $url . $options['param'] . '=' . ($current_page + 2) . $link_suffix . '">' . _lang('global.next') . " &raquo;</a>\n";
                }

                // last
                if ($endpage < $pages) {
                    $paging .= '<span class="paging-last-addon"> ... </span><a class="paging-last" href="' . $url . $options['param'] . '=' . $pages . $link_suffix . '" title="' . _lang('global.last') . '">' . $pages . "</a>\n";
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
            'current' => ($current_page + 1),
            'total' => $pages,
            'count' => $count,
            'first' => $start,
            'last' => (($end_item > $count - 1) ? $count - 1 : $end_item),
            'per_page' => $limit,
        ];
    }

    /**
     * Render paginator for table rows
     *
     * Additional supported options:
     * @see Paginator::paginate() for more options
     * -------------------------------------------
     * cond ('1')       row filter
     * alias (-)        table alias to use
     *
     * @param string $url base URL to add page parameter to
     * @param int $limit max item per page
     * @param string $table
     * @param array{
     *     param?: string,
     *     link_suffix?: string,
     *     auto_last?: bool,
     *     cond?: string,
     *     alias?: string,
     * } $options
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
    static function paginateTable(string $url, int $limit, string $table, array $options = []): array
    {
        $count = DB::result(DB::query(
            'SELECT COUNT(*) FROM ' . DB::escIdt($table) . (isset($options['alias']) ? " AS {$options['alias']}" : '')
            . ' WHERE ' . ($options['cond'] ?? '1')
        ));

        return self::paginate(
            $url,
            $limit,
            $count,
            $options
        );
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
     * @param array $pagingdata output of {@see Paginator::paginate()}
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
