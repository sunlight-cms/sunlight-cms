<?php

namespace Sunlight\Page;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\TreeFilterInterface;
use Sunlight\Database\TreeManager;
use Sunlight\Database\TreeReader;
use Sunlight\Database\TreeReaderOptions;
use Sunlight\Extend;

abstract class PageManager
{
    /** @var TreeManager|null */
    private static $treeManager;
    /** @var TreeReader|null */
    private static $treeReader;
    /** @var array */
    private static $pathCache = [];
    /** @var array */
    private static $childrenCache = [];

    /**
     * Nalezt stranku a nacist jeji data
     *
     * Oddelovace jsou ignorovany.
     *
     * @param array       $segments      segmenty
     * @param string|null $extra_columns sloupce navic (automaticky oddeleno carkou)
     * @param string|null $extra_joins   joiny navic (automaticky oddeleno mezerou)
     * @param string|null $extra_conds   podminky navic (automaticky oddeleno pomoci " AND (*conds*)")
     * @return array|bool false pri nenalezeni
     */
    static function find(array $segments, ?string $extra_columns = null, ?string $extra_joins = null, ?string $extra_conds = null)
    {
        // zaklad dotazu
        $sql = 'SELECT page.*';
        if ($extra_columns !== null) {
            $sql .= ',' . $extra_columns;
        }
        $sql .= ' FROM ' . _page_table . ' AS page';
        if ($extra_joins !== null) {
            $sql .= ' ' . $extra_joins;
        }

        // podminky
        $conds = [];

        // ignorovat oddelovace
        $conds[] = 'page.type!=' . _page_separator;

        // predane podminky
        if ($extra_conds !== null) {
            $extra_conds[] = '(' . $conds . ')';
        }

        // identifikator
        if (!empty($segments)) {
            $slugs = [];
            for ($i = count($segments); $i > 0; --$i) {
                $slugs[] = implode('/', array_slice($segments, 0, $i));
            }
            $conds[] = 'page.slug IN(' . DB::arr($slugs) . ')';
        } else {
            $indexPageId = Extend::fetch('page.find.index', [], _index_page_id);
            $conds[] = 'page.id=' . DB::val($indexPageId);
        }

        // dokoncit dotaz
        $sql .= ' WHERE ' . implode(' AND ', $conds);
        if (!empty($segments)) {
            $sql .= ' ORDER BY LENGTH(page.slug) DESC';
        }
        $sql .= ' LIMIT 1';

        // nacist data
        return DB::queryRow($sql);
    }

    /**
     * Ziskat data aktivni stranky
     *
     * Pouzitelne v kontextu webu.
     * Vraci pole v tomto formatu:
     *
     * array(
     *     cislo (ID stranky) nebo NULL,
     *     pole (vsechna data stranky) nebo NULL,
     * )
     *
     * @return array id, [data]
     */
    static function getActive(): array
    {
        $id = null;
        $data = null;

        if (_env === Core::ENV_WEB) {
            global $_index;

            if ($_index['type'] === _index_page) {
                $id = $_index['id'];
                $data = $GLOBALS['_page'];
            }
        }

        return [$id, $data];
    }

    /**
     * Zjistit, zda je alespon jedna z uvedenych stranek aktivni
     *
     * @param int[] $ids      seznam ID
     * @param bool  $children kontrolovat take potomky danych stranek
     * @return bool
     */
    static function isActive(array $ids, bool $children = false): bool
    {
        $result = false;

        // zjistit aktualni stranku
        [$currentId, $currentData] = self::getActive();

        if ($currentData !== null) {
            $currentLevel = $currentData['node_level'];
        } else {
            $currentLevel = null;
        }

        // kontrola shody ID
        foreach ($ids as $id) {
            if ($currentId == $id) {
                $result = true;
                break;
            }
        }

        // kontrola potomku
        if (!$result && $children) {
            $idMap = array_flip($ids);

            foreach (self::getPath($currentId, $currentLevel) as $page) {
                if (isset($idMap[$page['id']])) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Ziskat typy stranek
     *
     * @return array pole nazvu
     */
    static function getTypes(): array
    {
        return [
            _page_section => 'section',
            _page_category => 'category',
            _page_book => 'book',
            _page_separator => 'separator',
            _page_gallery => 'gallery',
            _page_link => 'link',
            _page_group => 'group',
            _page_forum => 'forum',
            _page_plugin => 'pluginpage',
        ];
    }

    /**
     * Ziskat typy registrovanych plugin stranek
     *
     * @return array idt => label
     */
    static function getPluginTypes(): array
    {
        static $cache = null;
        
        if ($cache === null) {
            $cache = [];
            Extend::call('page.plugin.reg', ['infos' => &$cache]);
        }

        return $cache;
    }

    /**
     * Ziskat data konkretni stranky
     *
     * @param int   $id
     * @param array $columns
     * @param bool  $addTreeColumns pridat vychozi sloupce pro strom
     * @return array|bool false pri selhani
     */
    static function getData(int $id, array $columns, bool $addTreeColumns = false)
    {
        if ($id === null || $id < 1) {
            return false;
        }

        if ($addTreeColumns) {
            $columns = self::prepareTreeColumns($columns);
        }

        return DB::queryRow('SELECT ' . DB::idtList($columns) . '  FROM ' . _page_table . ' WHERE id=' . DB::val($id));
    }

    /**
     * Ziskat tree reader pro strom stranek
     *
     * @return TreeManager
     */
    static function getTreeManager(): TreeManager
    {
        if (self::$treeManager === null) {
            self::$treeManager = new TreeManager(_page_table);
        }

        return self::$treeManager;
    }

    /**
     * Ziskat tree reader pro strom stranek
     *
     * @return TreeReader
     */
    static function getTreeReader(): TreeReader
    {
        if (self::$treeReader === null) {
            self::$treeReader = new TreeReader(_page_table);
        }

        return self::$treeReader;
    }

    /**
     * Nacist jednu uroven stranek
     *
     * @param int|null    $parentNodeId ID nadrazene stranky nebo null
     * @param string|null $sqlCond      SQL podminka
     * @param array|null  $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getSingleLevel(?int $parentNodeId, ?string $sqlCond = null, ?array $extraColumns = null): array
    {
        if ($parentNodeId === null) {
            $where = 'node_parent IS NULL';
        } else {
            $where = 'node_parent=' . DB::val($parentNodeId);
        }

        if ($sqlCond !== null) {
            $where .= ' AND (' . $sqlCond . ')';
        }

        $columns = DB::idtList(array_merge(self::getTreeReader()->getSystemColumns(), self::prepareTreeColumns($extraColumns)));
        $query = DB::query('SELECT ' . $columns . ' FROM ' . _page_table . ' WHERE ' . $where . ' ORDER BY ord');

        $pages = [];
        while ($page = DB::row($query)) {
            $pages[] = $page;
        }
        DB::free($query);

        return $pages;
    }

    /**
     * Nacist strom stranek
     *
     * @param int|null                 $nodeId       ID vychozi stranky
     * @param int|null                 $nodeDepth    hloubka stromu, je-li znama
     * @param TreeFilterInterface|null $filter       filtr polozek
     * @param array|null               $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getTree(
        ?int $nodeId = null,
        ?int $nodeDepth = null,
        ?TreeFilterInterface $filter = null,
        ?array $extraColumns = null
    ) : array{
        return self::getTreeReader()->getTree(
            self::getTreeReaderOptions($nodeId, $nodeDepth, $filter, $extraColumns)
        );
    }

    /**
     * Nacist plochy strom stranek
     *
     * @param int|null                 $nodeId       ID vychozi stranky
     * @param int|null                 $nodeDepth    hloubka stromu, je-li znama
     * @param TreeFilterInterface|null $filter       filtr polozek (asociativni pole)
     * @param array|null               $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getFlatTree(
        ?int $nodeId = null,
        ?int $nodeDepth = null,
        ?TreeFilterInterface $filter = null,
        array $extraColumns = null
    ) : array{
        return self::getTreeReader()->getFlatTree(
            self::getTreeReaderOptions($nodeId, $nodeDepth, $filter, $extraColumns)
        );
    }

    /**
     * Nacist potomky dane stranky
     *
     * @param int|null                 $nodeId       ID stranky
     * @param int|null                 $nodeDepth    hloubka stranky (node_depth), je-li znama
     * @param bool                     $flat         vratit plochy strom 1/0
     * @param TreeFilterInterface|null $filter       filtr polozek (asociativni pole)
     * @param array|null               $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getChildren(
        ?int $nodeId,
        ?int $nodeDepth = null,
        bool $flat = true,
        ?TreeFilterInterface $filter = null,
        ?array $extraColumns = null
    ) : array{
        $canBeCached = $filter === null && $extraColumns === null;

        if ($canBeCached && isset(self::$childrenCache[$nodeId])) {
            return self::$childrenCache[$nodeId];
        }

        $children = self::getTreeReader()->getChildren(
            self::getTreeReaderOptions($nodeId, $nodeDepth, $filter, $extraColumns),
            $flat
        );
        
        if ($canBeCached) {
            self::$childrenCache[$nodeId] = $children;
        }

        return $children;
    }

    /**
     * Nacist korenove stranky (uroven=0)
     *
     * @param TreeFilterInterface|null $filter
     * @param array|null               $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getRootPages(TreeFilterInterface $filter = null, ?array $extraColumns = null): array
    {
        $options = self::getTreeReaderOptions(null, 0, $filter, $extraColumns);

        return self::getTreeReader()->getTree($options);
    }

    /**
     * Nacist cestu ("drobecky")
     *
     * @param int        $id           identifikator stranky
     * @param int|null   $level        uroven stranky (node_level), je-li znama
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getPath(int $id, ?int $level = null, ?array $extraColumns = null): array
    {
        $canBeCached = $extraColumns === null;

        if ($canBeCached && isset(self::$pathCache[$id])) {
            return self::$pathCache[$id];
        }

        $path = self::getTreeReader()->getPath(
            self::prepareTreeColumns($extraColumns),
            $id,
            $level
        );

        if ($canBeCached) {
            self::$pathCache[$id] = $path;
        }

        return $path;
    }

    /**
     * Pripravit seznam sloupcu pro nacteni stromu
     *
     * @param array|null $extraColumns
     * @return array
     */
    static function prepareTreeColumns(?array $extraColumns = null): array
    {
        if ($extraColumns === null) {
            $extraColumns = [];
        }

        $columns = ['title', 'slug', 'type', 'type_idt', 'ord', 'visible', 'public', 'level'];

        Extend::call('page.tree_columns', ['extra_columns' => &$extraColumns]);
        if (!empty($extraColumns)) {
            $columns = array_merge($columns, $extraColumns);
        }

        return $columns;
    }

    /**
     * @param int|null                 $nodeId
     * @param int|null                 $nodeDepth
     * @param TreeFilterInterface|null $filter
     * @param array|null               $extraColumns
     * @return TreeReaderOptions
     */
    private static function getTreeReaderOptions(?int $nodeId, ?int $nodeDepth, ?TreeFilterInterface $filter = null, ?array $extraColumns = null): TreeReaderOptions
    {
        $options = new TreeReaderOptions();

        $options->columns = self::prepareTreeColumns($extraColumns);
        $options->nodeId = $nodeId;
        $options->nodeDepth = $nodeDepth;
        $options->filter = $filter;
        $options->sortBy = 'ord';

        return $options;
    }
}
