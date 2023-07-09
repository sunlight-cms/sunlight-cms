<?php

namespace Sunlight\Page;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\TreeFilterInterface;
use Sunlight\Database\TreeManager;
use Sunlight\Database\TreeReader;
use Sunlight\Database\TreeReaderOptions;
use Sunlight\Extend;
use Sunlight\Settings;
use Sunlight\WebState;

abstract class Page
{
    /**
     * Map of page types to readable names
     */
    const TYPES = [
        self::SECTION => 'section',
        self::CATEGORY => 'category',
        self::BOOK => 'book',
        self::SEPARATOR => 'separator',
        self::GALLERY => 'gallery',
        self::LINK => 'link',
        self::GROUP => 'group',
        self::FORUM => 'forum',
        self::PLUGIN => 'pluginpage',
    ];

    /**
     * Section page type
     *
     * var1:    comments enabled 1/0
     * var2:    *unused*
     * var3:    locked comments 1/0
     * var4:    *unused*
     */
    const SECTION = 1;

    /**
     * Category page type
     *
     * var1:    article order type (1 = time DESC, 2 = id DESC, 3 = title ASC, 4 = title DESC)
     * var2:    number of articles per page
     * var3:    show article info 1/0
     * var4:    show article thumbnails 1/0
     */
    const CATEGORY = 2;

    /**
     * Book page type
     *
     * var1:    allow guest posts 1/0
     * var2:    number of posts per page
     * var3:    locked 1/0
     * var4:    *unused*
     */
    const BOOK = 3;

    /**
     * Separator page type
     *
     * var1:    *unused*
     * var2:    *unused*
     * var3:    *unused*
     * var4:    *unused*
     */
    const SEPARATOR = 4;

    /**
     * Gallery page type
     *
     * var1:    number images per row (-1 = don't make a table)
     * var2:    number of images per page
     * var3:    thumbnail height
     * var4:    thumbnail width
     */
    const GALLERY = 5;

    /**
     * Link page type
     *
     * var1:    open in new window 1/0
     * var2:    *unused*
     * var3:    *unused*
     * var4:    *unused*
     */
    const LINK = 6;

    /**
     * Group page type
     *
     * var1:    show item info 1/0
     * var2:    *unused*
     * var3:    *unused*
     * var4:    *unused*
     */
    const GROUP = 7;

    /**
     * Forum page type
     *
     * var1:    number of topics per page
     * var2:    locked 1/0
     * var3:    allow guest topics 1/0
     * var4:    *unused*
     */
    const FORUM = 8;

    /**
     * Plugin page type
     *
     * var1:    *plugin-implementation dependent*
     * var2:    *plugin-implementation dependent*
     * var3:    *plugin-implementation dependent*
     * var4:    *plugin-implementation dependent*
     */
    const PLUGIN = 9;
    
    /** @var TreeManager|null */
    private static $treeManager;
    /** @var TreeReader|null */
    private static $treeReader;
    /** @var array */
    private static $pathCache = [];
    /** @var array */
    private static $childrenCache = [];

    /**
     * Find a page and load its data
     *
     * @param array $segments segments
     * @param string|null $extra_columns extra columns to load (automatically separated by a comma)
     * @param string|null $extra_joins extra joins (automatically separated by a space)
     * @param string|null $extra_conds extra conditions (automatically separated by ' AND ')
     * @return array|false
     */
    static function find(array $segments, ?string $extra_columns = null, ?string $extra_joins = null, ?string $extra_conds = null)
    {
        // basic query
        $sql = 'SELECT page.*';

        if ($extra_columns !== null) {
            $sql .= ',' . $extra_columns;
        }

        $sql .= ' FROM ' . DB::table('page') . ' AS page';

        if ($extra_joins !== null) {
            $sql .= ' ' . $extra_joins;
        }

        // conditions
        $conds = [];

        // ignore separators
        $conds[] = 'page.type!=' . self::SEPARATOR;

        // extra conditions
        if ($extra_conds !== null) {
            $conds[] = '(' . $extra_conds . ')';
        }

        // identifier
        if (!empty($segments)) {
            $slugs = [];

            for ($i = count($segments); $i > 0; --$i) {
                $slugs[] = implode('/', array_slice($segments, 0, $i));
            }

            $conds[] = 'page.slug IN(' . DB::arr($slugs) . ')';
        } else {
            $indexPageId = Extend::fetch('page.find.index', [], Settings::get('index_page_id'));
            $conds[] = 'page.id=' . DB::val($indexPageId);
        }

        // finalize query
        $sql .= ' WHERE ' . implode(' AND ', $conds);

        if (!empty($segments)) {
            $sql .= ' ORDER BY LENGTH(page.slug) DESC';
        }

        $sql .= ' LIMIT 1';

        // load data
        return DB::queryRow($sql);
    }

    /**
     * Get data of active page
     *
     * @return array{0: numeric-string|null, 1: array|null}
     */
    static function getActive(): array
    {
        $id = null;
        $data = null;

        if (Core::$env === Core::ENV_WEB) {
            global $_index;

            if ($_index->type === WebState::PAGE) {
                $id = $_index->id;
                $data = $GLOBALS['_page'];
            }
        }

        return [$id, $data];
    }

    /**
     * See if any of the given page IDs is the active page
     *
     * @param int[] $ids list of IDs
     * @param bool $children check page children 1/0
     */
    static function isActive(array $ids, bool $children = false): bool
    {
        // determine current page
        [$currentId, $currentData] = self::getActive();

        if ($currentId === null) {
            return false;
        }

        // determine current level
        if ($currentData !== null) {
            $currentLevel = $currentData['node_level'];
        } else {
            $currentLevel = null;
        }

        // check IDs
        if (in_array($currentId, $ids)) {
            return true;
        }

        // check children
        if ($children) {
            $idMap = array_flip($ids);

            foreach (self::getPath($currentId, $currentLevel) as $page) {
                if (isset($idMap[$page['id']])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get types of registered plugin pages
     *
     * @return array<string, string> idt => label
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
     * Get data of a specific page
     *
     * @param bool $addTreeColumns load tree columns as well 1/0
     * @return array|false
     */
    static function getData(int $id, array $columns, bool $addTreeColumns = false)
    {
        if ($id < 1) {
            return null;
        }

        if ($addTreeColumns) {
            $columns = self::prepareTreeColumns($columns);
        }

        return DB::queryRow('SELECT ' . DB::idtList($columns) . '  FROM ' . DB::table('page') . ' WHERE id=' . DB::val($id));
    }

    /**
     * Get page tree manager
     */
    static function getTreeManager(): TreeManager
    {
        if (self::$treeManager === null) {
            self::$treeManager = new TreeManager('page');
        }

        return self::$treeManager;
    }

    /**
     * Get page tree reader
     */
    static function getTreeReader(): TreeReader
    {
        if (self::$treeReader === null) {
            self::$treeReader = new TreeReader('page');
        }

        return self::$treeReader;
    }

    /**
     * Load a single level of pages
     *
     * @param int|null $parentPageId parent page ID or null
     * @param string|null $sqlCond SQL condition
     * @param array|null $extraColumns list of additional columns to load
     */
    static function getSingleLevel(?int $parentPageId, ?string $sqlCond = null, ?array $extraColumns = null): array
    {
        if ($parentPageId === null) {
            $where = 'node_parent IS NULL';
        } else {
            $where = 'node_parent=' . DB::val($parentPageId);
        }

        if ($sqlCond !== null) {
            $where .= ' AND (' . $sqlCond . ')';
        }

        $columns = DB::idtList(array_merge(self::getTreeReader()->getTreeColumns(), self::prepareTreeColumns($extraColumns)));
        $query = DB::query('SELECT ' . $columns . ' FROM ' . DB::table('page') . ' WHERE ' . $where . ' ORDER BY ord');

        $pages = [];

        while ($page = DB::row($query)) {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * Load a page tree
     *
     * @param int|null $pageId only load this page and its children
     * @param int|null $depth depth, if known
     * @param TreeFilterInterface|null $filter page filter
     * @param array|null $extraColumns list of additional columns to load
     */
    static function getTree(
        ?int $pageId = null,
        ?int $depth = null,
        ?TreeFilterInterface $filter = null,
        ?array $extraColumns = null
    ): array {
        return self::getTreeReader()->getTree(
            self::getTreeReaderOptions($pageId, $depth, $filter, $extraColumns)
        );
    }

    /**
     * Load a flat page tree
     *
     * @param int|null $pageId only load this page and its children
     * @param int|null $depth depth, if known
     * @param TreeFilterInterface|null $filter page filter
     * @param array|null $extraColumns list of additional columns to load
     */
    static function getFlatTree(
        ?int $pageId = null,
        ?int $depth = null,
        ?TreeFilterInterface $filter = null,
        array $extraColumns = null
    ) : array {
        return self::getTreeReader()->getFlatTree(
            self::getTreeReaderOptions($pageId, $depth, $filter, $extraColumns)
        );
    }

    /**
     * Load children of the given page
     *
     * @param int|null $pageId page ID or NULL (root)
     * @param int|null $depth depth, if known
     * @param bool $flat return a flat tree 1/0
     * @param TreeFilterInterface|null $filter page filter
     * @param array|null $extraColumns list of additional columns to load
     */
    static function getChildren(
        ?int $pageId,
        ?int $depth = null,
        bool $flat = true,
        ?TreeFilterInterface $filter = null,
        ?array $extraColumns = null
    ): array {
        $canBeCached = $filter === null && $extraColumns === null;

        if ($canBeCached && isset(self::$childrenCache[$pageId])) {
            return self::$childrenCache[$pageId];
        }

        $children = self::getTreeReader()->getChildren(
            self::getTreeReaderOptions($pageId, $depth, $filter, $extraColumns),
            $flat
        );
        
        if ($canBeCached) {
            self::$childrenCache[$pageId] = $children;
        }

        return $children;
    }

    /**
     * Load root pages
     *
     * @param array|null $extraColumns list of additional columns to load
     */
    static function getRootPages(TreeFilterInterface $filter = null, ?array $extraColumns = null): array
    {
        $options = self::getTreeReaderOptions(null, 0, $filter, $extraColumns);

        return self::getTreeReader()->getFlatTree($options);
    }

    /**
     * Load a path ("breadcrumbs")
     *
     * @param int $id page identifier
     * @param int|null $level page level, if known
     * @param array|null $extraColumns list of additional columns to load
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
     * Prepare a list of tree columns
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

    private static function getTreeReaderOptions(?int $pageId, ?int $depth, ?TreeFilterInterface $filter = null, ?array $extraColumns = null): TreeReaderOptions
    {
        $options = new TreeReaderOptions();

        $options->columns = self::prepareTreeColumns($extraColumns);
        $options->nodeId = $pageId;
        $options->nodeDepth = $depth;
        $options->filter = $filter;
        $options->sortBy = 'ord';

        return $options;
    }
}
