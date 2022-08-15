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
     * Section page type
     *
     * var1:    comments enabled 1/0
     * var2:    *unused*
     * var3:    lockec comments 1/0
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
     * Nalezt stranku a nacist jeji data
     *
     * Oddelovace jsou ignorovany.
     *
     * @param array $segments segmenty
     * @param string|null $extra_columns sloupce navic (automaticky oddeleno carkou)
     * @param string|null $extra_joins joiny navic (automaticky oddeleno mezerou)
     * @param string|null $extra_conds podminky navic (automaticky oddeleno pomoci " AND (*conds*)")
     * @return array|bool false pri nenalezeni
     */
    static function find(array $segments, ?string $extra_columns = null, ?string $extra_joins = null, ?string $extra_conds = null)
    {
        // zaklad dotazu
        $sql = 'SELECT page.*';
        if ($extra_columns !== null) {
            $sql .= ',' . $extra_columns;
        }
        $sql .= ' FROM ' . DB::table('page') . ' AS page';
        if ($extra_joins !== null) {
            $sql .= ' ' . $extra_joins;
        }

        // podminky
        $conds = [];

        // ignorovat oddelovace
        $conds[] = 'page.type!=' . self::SEPARATOR;

        // predane podminky
        if ($extra_conds !== null) {
            $conds[] = '(' . $conds . ')';
        }

        // identifikator
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
     * Zjistit, zda je alespon jedna z uvedenych stranek aktivni
     *
     * @param int[] $ids seznam ID
     * @param bool $children kontrolovat take potomky danych stranek
     */
    static function isActive(array $ids, bool $children = false): bool
    {
        $result = false;

        // zjistit aktualni stranku
        [$currentId, $currentData] = self::getActive();

        // stranka bez ID (napr. modul)
        if ($currentId === null) {
            return false;
        }

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
     * @param bool $addTreeColumns pridat vychozi sloupce pro strom
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

        return DB::queryRow('SELECT ' . DB::idtList($columns) . '  FROM ' . DB::table('page') . ' WHERE id=' . DB::val($id));
    }

    /**
     * Ziskat tree reader pro strom stranek
     */
    static function getTreeManager(): TreeManager
    {
        if (self::$treeManager === null) {
            self::$treeManager = new TreeManager('page');
        }

        return self::$treeManager;
    }

    /**
     * Ziskat tree reader pro strom stranek
     */
    static function getTreeReader(): TreeReader
    {
        if (self::$treeReader === null) {
            self::$treeReader = new TreeReader('page');
        }

        return self::$treeReader;
    }

    /**
     * Nacist jednu uroven stranek
     *
     * @param int|null $parentNodeId ID nadrazene stranky nebo null
     * @param string|null $sqlCond SQL podminka
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
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
        $query = DB::query('SELECT ' . $columns . ' FROM ' . DB::table('page') . ' WHERE ' . $where . ' ORDER BY ord');

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
     * @param int|null $nodeId ID vychozi stranky
     * @param int|null $nodeDepth hloubka stromu, je-li znama
     * @param TreeFilterInterface|null $filter filtr polozek
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
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
     * @param int|null $nodeId ID vychozi stranky
     * @param int|null $nodeDepth hloubka stromu, je-li znama
     * @param TreeFilterInterface|null $filter filtr polozek (asociativni pole)
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
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
     * @param int|null $nodeId ID stranky
     * @param int|null $nodeDepth hloubka stranky (node_depth), je-li znama
     * @param bool $flat vratit plochy strom 1/0
     * @param TreeFilterInterface|null $filter filtr polozek (asociativni pole)
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
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
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
     */
    static function getRootPages(TreeFilterInterface $filter = null, ?array $extraColumns = null): array
    {
        $options = self::getTreeReaderOptions(null, 0, $filter, $extraColumns);

        return self::getTreeReader()->getTree($options);
    }

    /**
     * Nacist cestu ("drobecky")
     *
     * @param int $id identifikator stranky
     * @param int|null $level uroven stranky (node_level), je-li znama
     * @param array|null $extraColumns pole s extra sloupci, ktere se maji nacist
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
