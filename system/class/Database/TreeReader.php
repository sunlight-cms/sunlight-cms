<?php

namespace Sunlight\Database;

use Sunlight\Database\Database as DB;

class TreeReader
{
    /** @var string */
    private $table;
    /** @var string */
    private $childrenIndex;
    /** @var string */
    private $idColumn;
    /** @var string */
    private $parentColumn;
    /** @var string */
    private $levelColumn;
    /** @var string */
    private $depthColumn;

    /**
     * @param string $table table name (no prefix)
     * @param string|null $childrenIndex nazev indexu pro kolekce potomku uzlu
     * @param string|null $idColumn nazev sloupce pro id
     * @param string|null $parentColumn nazev sloupce pro nadrazeny uzel
     * @param string|null $levelColumn nazev sloupce pro uroven
     * @param string|null $depthColumn nazev sloupce pro hloubku
     */
    function __construct(string $table, ?string $childrenIndex = null, ?string $idColumn = null, ?string $parentColumn = null, ?string $levelColumn = null, ?string $depthColumn = null)
    {
        $this->table = $table;
        $this->childrenIndex = $childrenIndex ?: 'children';
        $this->idColumn = $idColumn ?: 'id';
        $this->parentColumn = $parentColumn ?: 'node_parent';
        $this->levelColumn = $levelColumn ?: 'node_level';
        $this->depthColumn = $depthColumn ?: 'node_depth';
    }

    /**
     * Get path to the given node (from root)
     *
     * @param array $columns list of additional columns to load
     * @param int $nodeId node identifier
     * @param int|null $nodeLevel node level, if known (saves 1 query)
     */
    function getPath(array $columns, int $nodeId, ?int $nodeLevel = null): array
    {
        return $this->loadPath($columns, $nodeId, $nodeLevel);
    }

    /**
     * Load tree as a structured array
     */
    function getTree(TreeReaderOptions $options): array
    {
        return $this->structureTree(
            $this->loadTree($options),
            $options->nodeId
        );
    }

    /**
     * Load children of the given node
     *
     * @param bool $flat return a flat tree if TRUE, otherwise a structured array
     */
    function getChildren(TreeReaderOptions $options, bool $flat = true): array
    {
        if ($flat) {
            $tree = $this->getFlatTree($options);
        } else {
            $tree = $this->getTree($options);
        }

        return $this->extractChildren($tree, $options->nodeId, $flat);
    }

    /**
     * Extract children from a tree with only one root
     *
     * @param int|null $rootNodeId root node identifier
     * @param bool $flat TRUE if this is a tree
     */
    function extractChildren(array $tree, ?int $rootNodeId, bool $flat): array
    {
        if ($flat) {
            if ($rootNodeId !== null && !empty($tree)) {
                reset($tree);
                unset($tree[key($tree)]);
            }
        } elseif ($rootNodeId !== null && !empty($tree)) {
            $tree = $tree[0][$this->childrenIndex];
        }

        return $tree;
    }

    /**
     * Load a flat tree
     */
    function getFlatTree(TreeReaderOptions $options): array
    {
        return $this->sortTree(
            $this->loadTree($options),
            $options->nodeId
        );
    }

    /**
     * Flatten a tree
     *
     * @param array $tree structured tree
     */
    function flattenTree(array $tree): array
    {
        if (empty($tree)) {
            return [];
        }

        $list = [];
        $stack = [];
        $frame = [$tree, 0];
        do {
            for ($i = $frame[1]; isset($frame[0][$i]); ++$i) {
                // get children
                $children = $frame[0][$i][$this->childrenIndex];
                unset($frame[0][$i][$this->childrenIndex]);

                // add node to list
                $list[] = $frame[0][$i];

                // traverse children?
                if (!empty($children)) {
                    // stop and continue with children
                    $stack[] = [$frame[0], $i + 1];
                    $frame = [$children, 0];
                    continue 2;
                }
            }

            $frame = array_pop($stack);
        } while ($frame !== null);

        return $list;
    }

    /**
     * Convert a list of nodes into a structured tree
     *
     * @param int|null $rootId root node identifier
     */
    function structureTree(array $nodes, ?int $rootId = null): array
    {
        $tree = [];
        $childrenMap = [];

        foreach ($nodes as &$node) {
            $node[$this->childrenIndex] = [];

            // add node
            if ($node[$this->parentColumn] !== null && ($rootId === null || $rootId != $node[$this->idColumn])) {
                // as a child
                if ($node[$this->depthColumn] > 0) {
                    $nodeIndex = array_push($childrenMap[$node[$this->parentColumn]], $node) - 1;
                    $childrenMap[$node[$this->idColumn]] = &$childrenMap[$node[$this->parentColumn]][$nodeIndex][$this->childrenIndex];
                } else {
                    $childrenMap[$node[$this->parentColumn]][] = $node;
                }
            } else {
                // as root
                $childrenMap[$node[$this->idColumn]] = &$tree[array_push($tree, $node) - 1][$this->childrenIndex];
            }
        }

        return $tree;
    }

    /**
     * Sort node list based on the hierarchy
     *
     * @param int|null $rootId root node identifier
     */
    function sortTree(array $nodes, ?int $rootId = null): array
    {
        $output = [];

        $stack = [];
        $parentId = $rootId;

        if ($rootId !== null) {
            foreach ($nodes as $nodeId => $node) {
                if ($nodeId == $rootId) {
                    $output[$nodeId] = $node;
                    unset($nodes[$nodeId]);

                    break;
                }
            }
        }

        do {
            foreach ($nodes as $nodeId => $node) {
                $nodeParentId = $node[$this->parentColumn];

                if (
                    $parentId === null && $nodeParentId === null
                    || $parentId !== null && $parentId == $nodeParentId
                ) {
                    $output[$node[$this->idColumn]] = $node;
                    unset($nodes[$nodeId]);

                    if ($node[$this->depthColumn] > 0) {
                        $stack[] = $parentId;
                        $parentId = $node[$this->idColumn];

                        continue 2;
                    }
                }
            }

            if (!empty($stack)) {
                $parentId = array_pop($stack);
            } else {
                $parentId = false;
            }
        } while ($parentId !== false);

        return $output;
    }

    /**
     * Load a tree
     *
     * @return array node list ordered by level (ascending)
     */
    function loadTree(TreeReaderOptions $options): array
    {
        // determine tree depth
        $nodeDepth = $options->nodeDepth ?? $this->getDepth($options->nodeId);

        // prepare columns
        $columns = array_merge($this->getTreeColumns(), $options->columns);
        $columnCount = count($columns);

        // prepare filter
        $filterSql = $options->filter ? "%__node__%.`{$this->depthColumn}`>0 OR ({$options->filter->getNodeSql($this)})" : null;

        // compose query
        $sql = 'SELECT ';
        for ($i = 0; $i < $columnCount; ++$i) {
            if ($i !== 0) {
                $sql .= ',';
            }
            $sql .= 'r.' . $columns[$i];
        }
        for ($i = 0; $i < $nodeDepth; ++$i) {
            for ($j = 0; $j < $columnCount; ++$j) {
                $sql .= ',n' . $i . '.' . $columns[$j];
            }
        }

        $sql .= ' FROM ' . DB::table($this->table) . ' r';
        $parentAlias = 'r';
        for ($i = 0; $i < $nodeDepth; ++$i) {
            $nodeAlias = 'n' . $i;
            $sql .= sprintf(
                ' LEFT OUTER JOIN %s %s ON(%2$s.%s=%s.%s%s)',
                DB::table($this->table),
                $nodeAlias,
                $this->parentColumn,
                $parentAlias,
                $this->idColumn,
                ($filterSql !== null)
                    ? ' AND (' . str_replace('%__node__%', $nodeAlias, $filterSql) . ')'
                    : ''
            );
            $parentAlias = $nodeAlias;
        }
        $sql .= ' WHERE r.';
        if ($options->nodeId === null) {
            $sql .= $this->levelColumn . '=0';
        } else {
            $sql .= $this->idColumn . '=' . DB::val($options->nodeId);
        }
        if ($filterSql !== null) {
            $sql .= ' AND (' . str_replace('%__node__%', 'r', $filterSql) . ')';
        }

        // load nodes
        $nodeMap = [];
        $query = DB::query($sql);
        while ($row = DB::rown($query)) {
            for ($i = 0; isset($row[$i]); $i += $columnCount) {
                if (!isset($nodeMap[$row[$i]])) {
                    $nodeMap[$row[$i]] = [];
                    for ($j = 0; $j < $columnCount; ++$j) {
                        $nodeMap[$row[$i]][$columns[$j]] = $row[$i + $j];
                    }
                }
            }
        }
        DB::free($query);

        // sort nodes
        $levelColumn = $this->levelColumn;
        uasort(
            $nodeMap,
            function (array $a, array $b) use ($options, $levelColumn) {
                if ($a[$levelColumn] > $b[$levelColumn]) {
                    return 1;
                }
                if ($a[$levelColumn] == $b[$levelColumn]) {
                    if ($options->sortBy !== null) {
                        return strnatcmp($a[$options->sortBy], $b[$options->sortBy]) * ($options->sortAsc ? 1 : -1);
                    }

                    return 0;
                }

                return -1;
            }
        );

        // apply filter
        if ($options->filter) {
            // find invalid nodes
            $invalidNodes = [];

            foreach ($nodeMap as $id => $node) {
                if (!$options->filter->filterNode($node, $this)) {
                    if ($node[$this->depthColumn] > 0) {
                        // invalid node with children - add to list
                        $invalidNodes[$id] = $node[$this->levelColumn];
                    } else {
                        // invalid node with no children - remove
                        unset($nodeMap[$id]);
                    }
                }
            }

            // remove branches with only invalid nodes
            if (!empty($invalidNodes)) {
                // order invalid nodes by level (ascending)
                arsort($invalidNodes, SORT_NUMERIC);

                // prepare maps
                $nodeIndexToIdMap = array_keys($nodeMap);
                $nodeIdToIndexMap = array_flip($nodeIndexToIdMap);

                // traverse invalid nodes
                foreach ($invalidNodes as $invalidNodeId => &$invalidNodeLevel) {
                    if ($invalidNodeLevel === false) {
                        continue;
                    }

                    $foundValidChild = false;
                    $childLevel = $invalidNodeLevel + 1;

                    for ($i = $nodeIdToIndexMap[$invalidNodeId] + 1; isset($nodeIndexToIdMap[$i]); ++$i) {
                        if (!isset($nodeMap[$nodeIndexToIdMap[$i]])) {
                            continue;
                        }
                        if (
                            $nodeMap[$nodeIndexToIdMap[$i]][$this->parentColumn] == $invalidNodeId
                            && !isset($invalidNodes[$nodeIndexToIdMap[$i]])
                            && $options->filter->acceptInvalidNodeWithValidChild(
                                $nodeMap[$invalidNodeId],
                                $nodeMap[$nodeIndexToIdMap[$i]],
                                $this
                            )
                        ) {
                            $foundValidChild = true;
                            break;
                        }

                        if ($nodeMap[$nodeIndexToIdMap[$i]][$this->levelColumn] > $childLevel) {
                            break;
                        }
                    }

                    if ($foundValidChild) {
                        // mark whole path as valid
                        do {
                            $invalidNodes[$invalidNodeId] = false;
                            $invalidNodeId = $nodeMap[$invalidNodeId][$this->parentColumn];
                        } while ($invalidNodeId !== null && isset($nodeMap[$invalidNodeId]));
                    } else {
                        // remove node
                        unset($nodeMap[$invalidNodeId]);
                    }
                }
            }
        }

        return $nodeMap;
    }

    /**
     * Get table name
     */
    function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get list of tree columns
     */
    function getTreeColumns(): array
    {
        return [
            $this->idColumn,
            $this->parentColumn,
            $this->levelColumn,
            $this->depthColumn,
        ];
    }

    /**
     * Get identifier column
     */
    function getIdColumn(): string
    {
        return $this->idColumn;
    }

    /**
     * Get parent identifier column
     */
    function getParentColumn(): string
    {
        return $this->parentColumn;
    }

    /**
     * Get level column
     */
    function getLevelColumn(): string
    {
        return $this->levelColumn;
    }

    /**
     * Get depth column
     */
    function getDepthColumn(): string
    {
        return $this->depthColumn;
    }

    /**
     * Load path to a node (from root)
     */
    function loadPath(array $columns, int $nodeId, ?int $nodeLevel = null): array
    {
        // determine node level
        if ($nodeLevel === null) {
            $nodeLevel = $this->getLevel($nodeId);
        }

        // prepare columns
        $columns = array_merge(
            [$this->idColumn, $this->parentColumn, $this->levelColumn, $this->depthColumn],
            $columns
        );
        $columnCount = count($columns);

        // compose query
        $sql = 'SELECT ';
        for ($i = 0; $i <= $nodeLevel; ++$i) {
            for ($j = 0; $j < $columnCount; ++$j) {
                if ($i !== 0 || $j !== 0) {
                    $sql .= ',';
                }
                $sql .= 'n' . $i . '.' . $columns[$j];
            }
        }
        $sql .= ' FROM ' . DB::table($this->table) . ' n0';
        for ($i = 1; $i <= $nodeLevel; ++$i) {
            $sql .= sprintf(
                "\n JOIN %s n%s ON(n%2\$s.%s=n%s.%s)", DB::table($this->table), $i, $this->idColumn, $i - 1, $this->parentColumn
            );
        }
        $sql .= ' WHERE n0.' . $this->idColumn . '=' . DB::val($nodeId);

        // load nodes
        $nodes = [];
        $nodeIndex = 0;
        $query = DB::query($sql);
        $row = DB::rown($query);
        for ($i = $nodeLevel * $columnCount; isset($row[$i]); $i -= $columnCount) {
            for ($j = 0; $j < $columnCount; ++$j) {
                $nodes[$nodeIndex][$columns[$j]] = $row[$i + $j];
            }
            ++$nodeIndex;
        }
        DB::free($query);

        return $nodes;
    }

    /**
     * Get level and depth of given node or root
     *
     * @return array level, depth
     */
    function getLevelAndDepth(?int $nodeId): array
    {
        if ($nodeId === null) {
            // root
            return [
                0,
                $this->getDepth(null),
            ];
        }

        // node
        $data = DB::queryRow('SELECT ' . $this->levelColumn . ',' . $this->depthColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
        if ($data === false) {
            throw new \RuntimeException(sprintf('Node "%s" does not exist', $nodeId));
        }

        return [
            $data[$this->levelColumn],
            $data[$this->depthColumn],
        ];
    }

    /**
     * Get node level
     */
    function getLevel(?int $nodeId): int
    {
        if ($nodeId === null) {
            return 0;
        }

        $nodeLevel = DB::queryRow('SELECT ' . $this->levelColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
        if ($nodeLevel === false) {
            throw new \RuntimeException(sprintf('Node "%s" does not exist', $nodeId));
        }

        return $nodeLevel[$this->levelColumn];
    }

    /**
     * Get depth of given node or root
     */
    function getDepth(?int $nodeId): ?int
    {
        if ($nodeId === null) {
            $nodeDepth = DB::queryRow('SELECT MAX(' . $this->depthColumn . ') ' . $this->depthColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $this->levelColumn . '=0');
        } else {
            $nodeDepth = DB::queryRow('SELECT ' . $this->depthColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
        }

        if ($nodeDepth === false) {
            // nonexistent node or other error
            throw new \RuntimeException(
                ($nodeId === null) ? 'Failed to determine tree depth' : sprintf('Node "%s" does not exist', $nodeId)
            );
        }

        return $nodeDepth[$this->depthColumn] ?? 0;
    }
}
