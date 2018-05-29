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
    protected static $treeManager;
    /** @var TreeReader|null */
    protected static $treeReader;
    /** @var array */
    protected static $pathCache = array();
    /** @var array */
    protected static $childrenCache = array();

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
    static function getActive()
    {
        $id = null;
        $data = null;

        if (_env === Core::ENV_WEB) {
            global $_index;

            if ($_index['is_page']) {
                $id = $_index['id'];
                $data = $GLOBALS['_page'];
            }
        }

        return array($id, $data);
    }

    /**
     * Zjistit, zda je alespon jedna z uvedenych stranek aktivni
     *
     * @param int[] $ids      seznam ID
     * @param bool  $children kontrolovat take potomky danych stranek
     * @return bool
     */
    static function isActive(array $ids, $children = false)
    {
        $result = false;

        // zjistit aktualni stranku
        list($currentId, $currentData) = static::getActive();

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

            foreach (static::getPath($currentId, $currentLevel) as $page) {
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
    static function getTypes()
    {
        return array(
            _page_section => 'section',
            _page_category => 'category',
            _page_book => 'book',
            _page_separator => 'separator',
            _page_gallery => 'gallery',
            _page_link => 'link',
            _page_group => 'group',
            _page_forum => 'forum',
            _page_plugin => 'pluginpage',
        );
    }

    /**
     * Ziskat typy registrovanych plugin stranek
     *
     * @return array idt => label
     */
    static function getPluginTypes()
    {
        static $cache = null;
        
        if ($cache === null) {
            $cache = array();
            Extend::call('page.plugin.reg', array('infos' => &$cache));
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
    static function getData($id, array $columns, $addTreeColumns = false)
    {
        if ($id === null || $id < 1) {
            return false;
        }

        if ($addTreeColumns) {
            $columns = static::prepareTreeColumns($columns);
        }

        return DB::queryRow('SELECT ' . DB::idtList($columns) . '  FROM ' . _root_table . ' WHERE id=' . DB::val($id));
    }

    /**
     * Ziskat tree reader pro strom stranek
     *
     * @return TreeManager
     */
    static function getTreeManager()
    {
        if (static::$treeManager === null) {
            static::$treeManager = new TreeManager(_root_table);
        }

        return static::$treeManager;
    }

    /**
     * Ziskat tree reader pro strom stranek
     *
     * @return TreeReader
     */
    static function getTreeReader()
    {
        if (static::$treeReader === null) {
            static::$treeReader = new TreeReader(_root_table);
        }

        return static::$treeReader;
    }

    /**
     * Nacist jednu uroven stranek
     *
     * @param int|null   $parentNodeId ID nadrazene stranky nebo null
     * @param string     $sqlCond      SQL podminka
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getSingleLevel($parentNodeId, $sqlCond = null, array $extraColumns = null)
    {
        if ($parentNodeId === null) {
            $where = 'node_parent IS NULL';
        } else {
            $where = 'node_parent=' . DB::val($parentNodeId);
        }

        if ($sqlCond !== null) {
            $where .= ' AND (' . $sqlCond . ')';
        }

        $columns = DB::idtList(array_merge(static::getTreeReader()->getSystemColumns(), static::prepareTreeColumns($extraColumns)));
        $query = DB::query('SELECT ' . $columns . ' FROM ' . _root_table . ' WHERE ' . $where . ' ORDER BY ord');

        $pages = array();
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
        $nodeId = null,
        $nodeDepth = null,
        TreeFilterInterface $filter = null,
        array $extraColumns = null
    ) {
        return static::getTreeReader()->getTree(
            static::getTreeReaderOptions($nodeId, $nodeDepth, $filter, $extraColumns)
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
        $nodeId = null,
        $nodeDepth = null,
        TreeFilterInterface $filter = null,
        array $extraColumns = null
    ) {
        return static::getTreeReader()->getFlatTree(
            static::getTreeReaderOptions($nodeId, $nodeDepth, $filter, $extraColumns)
        );
    }

    /**
     * Nacist potomky dane stranky
     *
     * @param int|null   $nodeId       ID stranky
     * @param int|null   $nodeDepth    hloubka stranky (node_depth), je-li znama
     * @param bool       $flat         vratit plochy strom 1/0
     * @param array|null $filter       filtr polozek (asociativni pole)
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getChildren(
        $nodeId,
        $nodeDepth = null,
        $flat = true,
        array $filter = null,
        array $extraColumns = null
    ) {
        $canBeCached = $filter === null && $extraColumns === null;

        if ($canBeCached && isset(static::$childrenCache[$nodeId])) {
            return static::$childrenCache[$nodeId];
        }

        $children = static::getTreeReader()->getChildren(
            static::getTreeReaderOptions($nodeId, $nodeDepth, $filter, $extraColumns),
            $flat
        );
        
        if ($canBeCached) {
            static::$childrenCache[$nodeId] = $children;
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
    static function getRootPages(TreeFilterInterface $filter = null, array $extraColumns = null)
    {
        $options = static::getTreeReaderOptions(null, 0, $filter, $extraColumns);

        return static::getTreeReader()->getTree($options);
    }

    /**
     * Nacist cestu ("drobecky")
     *
     * @param int        $id           identifikator stranky
     * @param int|null   $level        uroven stranky (node_level), je-li znama
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
     * @return array
     */
    static function getPath($id, $level = null, array $extraColumns = null)
    {
        $canBeCached = $extraColumns === null;

        if ($canBeCached && isset(static::$pathCache[$id])) {
            return static::$pathCache[$id];
        }

        $path = static::getTreeReader()->getPath(
            static::prepareTreeColumns($extraColumns),
            $id,
            $level
        );

        if ($canBeCached) {
            static::$pathCache[$id] = $path;
        }

        return $path;
    }

    /**
     * Pripravit seznam sloupcu pro nacteni stromu
     *
     * @param array|null $extraColumns
     * @return array
     */
    static function prepareTreeColumns(array $extraColumns = null)
    {
        if ($extraColumns === null) {
            $extraColumns = array();
        }

        $columns = array('title', 'slug', 'type', 'type_idt', 'ord', 'visible', 'public', 'level');

        Extend::call('page.tree_columns', array('extra_columns' => &$extraColumns));
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
    protected static function getTreeReaderOptions($nodeId, $nodeDepth, TreeFilterInterface $filter = null, array $extraColumns = null)
    {
        $options = new TreeReaderOptions();

        $options->columns = static::prepareTreeColumns($extraColumns);
        $options->nodeId = $nodeId;
        $options->nodeDepth = $nodeDepth;
        $options->filter = $filter;
        $options->sortBy = 'ord';

        return $options;
    }
}
