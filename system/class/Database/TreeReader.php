<?php

namespace Sunlight\Database;

use Sunlight\Database\Database as DB;

/**
 * Trida pro cteni ze stromove tabulky
 */
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
     * @param string      $table         nazev tabulky (bez prefixu)
     * @param string|null $childrenIndex nazev indexu pro kolekce potomku uzlu
     * @param string|null $idColumn      nazev sloupce pro id
     * @param string|null $parentColumn  nazev sloupce pro nadrazeny uzel
     * @param string|null $levelColumn   nazev sloupce pro uroven
     * @param string|null $depthColumn   nazev sloupce pro hloubku
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
     * Nacist cestu k danemu uzlu (vypis od korenu k danemu uzlu)
     *
     * @param array    $columns   pole, ktera maji byt nactena (systemove sloupce jsou nacteny vzdy)
     * @param int      $nodeId    ID uzlu
     * @param int|null $nodeLevel uroven uzlu, je-li znama (usetri 1 dotaz)
     * @return array
     */
    function getPath(array $columns, int $nodeId, ?int $nodeLevel = null): array
    {
        return $this->loadPath($columns, $nodeId, $nodeLevel);
    }

    /**
     * Nacist strom (strukturovane pole)
     *
     * @param TreeReaderOptions $options
     * @return array
     */
    function getTree(TreeReaderOptions $options): array
    {
        return $this->structureTree(
            $this->loadTree($options),
            $options->nodeId
        );
    }

    /**
     * Ziskat potomky daneho uzlu
     *
     * @param TreeReaderOptions $options
     * @param bool               $flat   true = vratit plochy strom (vypis uzlu v poradi hierarchie), false = strukturovane pole
     * @return array
     */
    function getChildren(TreeReaderOptions $options, bool $flat = true) : array{
        if ($flat) {
            $tree = $this->getFlatTree($options);
        } else {
            $tree = $this->getTree($options);
        }

        return $this->extractChildren($tree, $options->nodeId, $flat);
    }

    /**
     * Extrahovat potomky ze stromu, ktery obsahuje pouze 1 koren
     *
     * @param array    $tree       strom stranek
     * @param int|null $rootNodeId ID korenoveho uzlu
     * @param bool     $flat       zda se jedna o plochy strom 1/0
     * @return array
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
     * Nacist plochy strom (vypis uzlu v poradi hierarchie)
     *
     * @param TreeReaderOptions $options
     * @return array
     */
    function getFlatTree(TreeReaderOptions $options): array
    {
        return $this->sortTree(
            $this->loadTree($options),
            $options->nodeId
        );
    }

    /**
     * Zplostit strom
     *
     * @param array $tree strukturovany strom
     * @return array
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

                // ziskat potomky
                $children = $frame[0][$i][$this->childrenIndex];
                unset($frame[0][$i][$this->childrenIndex]);

                // vlozit uzel do seznamu
                $list[] = $frame[0][$i];

                // traverzovat potomky?
                if (!empty($children)) {
                    // prerusit tok a pokracovat potomky
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
     * Prevest seznam uzlu na strukturovany strom
     *
     * @param array    $nodes  pole uzlu
     * @param int|null $rootId ID korenoveho uzlu
     * @return array
     */
    function structureTree(array $nodes, ?int $rootId = null): array
    {
        $tree = [];
        $childrenMap = [];
        foreach ($nodes as &$node) {

            $node[$this->childrenIndex] = [];

            // pridat uzel
            if ($node[$this->parentColumn] !== null && ($rootId === null || $rootId != $node[$this->idColumn])) {
                // jako potomka
                if ($node[$this->depthColumn] > 0) {
                    $nodeIndex = array_push($childrenMap[$node[$this->parentColumn]], $node) - 1;
                    $childrenMap[$node[$this->idColumn]] = &$childrenMap[$node[$this->parentColumn]][$nodeIndex][$this->childrenIndex];
                } else {
                    $childrenMap[$node[$this->parentColumn]][] = $node;
                }
            } else {
                // jako koren
                $childrenMap[$node[$this->idColumn]] = &$tree[array_push($tree, $node) - 1][$this->childrenIndex];
            }
        }

        return $tree;
    }

    /**
     * Seradit seznam uzlu dle hierarchie
     *
     * @param array    $nodes  pole uzlu
     * @param int|null $rootId ID korenoveho uzlu
     * @return array
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
        } while($parentId !== false);

        return $output;
    }

    /**
     * Sestavit a provest dotaz na strom
     *
     * @param TreeReaderOptions $options
     * @return array seznam uzlu serazeny dle urovne vzestupne
     */
    function loadTree(TreeReaderOptions $options): array
    {
        // zjistit hloubku stromu
        $nodeDepth = $options->nodeDepth ?? $this->getDepth($options->nodeId);

        // pripravit sloupce
        $columns = array_merge($this->getSystemColumns(), $options->columns);
        $columnCount = count($columns);

        // pripravit filtr
        $filterSql = $options->filter ? "%__node__%.`{$this->depthColumn}`>0 OR ({$options->filter->getNodeSql($this)})" : null;

        // sestavit dotaz
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

        // nacist uzly
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

        // seradit uzly
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

        // aplikovat filtr
        if ($options->filter) {
            // vyhledat nevalidni uzly
            $invalidNodes = [];
            foreach ($nodeMap as $id => $node) {
                if (!$options->filter->filterNode($node, $this)) {
                    // zpracovat nevalidni uzel
                    if ($node[$this->depthColumn] > 0) {
                        // nevalidni uzel s potomky - pridat na seznam
                        $invalidNodes[$id] = $node[$this->levelColumn];
                    } else {
                        // nevalidni uzel bez potomku - rovnou odstranit
                        unset($nodeMap[$id]);
                    }
                }
            }

            // odstranit "hluche" vetve
            if (!empty($invalidNodes)) {
                // seradit neplatne uzly sestupne dle urovne
                arsort($invalidNodes, SORT_NUMERIC);

                // pripravit mapy
                $nodeIndexToIdMap = array_keys($nodeMap);
                $nodeIdToIndexMap = array_flip($nodeIndexToIdMap);

                // projit neplatne uzly
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
                        if ($nodeMap[$nodeIndexToIdMap[$i]][$this->parentColumn] == $invalidNodeId
                        && !isset($invalidNodes[$nodeIndexToIdMap[$i]])
                        && $options->filter->acceptInvalidNodeWithValidChild(
                            $nodeMap[$invalidNodeId],
                            $nodeMap[$nodeIndexToIdMap[$i]],
                            $this
                        )) {
                            $foundValidChild = true;
                            break;
                        }

                        if ($nodeMap[$nodeIndexToIdMap[$i]][$this->levelColumn] > $childLevel) {
                            break;
                        }
                    }

                    if ($foundValidChild) {
                        // oznacit celou cestu jako validni
                        do {
                            $invalidNodes[$invalidNodeId] = false;
                            $invalidNodeId = $nodeMap[$invalidNodeId][$this->parentColumn];
                        } while($invalidNodeId !== null);
                    } else {
                        // odstranit uzel
                        unset($nodeMap[$invalidNodeId]);
                    }
                }
            }
        }

        return $nodeMap;
    }

    /**
     * Ziskat nazev tabulky
     *
     * @return string
     */
    function getTable(): string
    {
        return $this->table;
    }

    /**
     * Vratit seznam systemovych sloupcu
     *
     * @return array
     */
    function getSystemColumns(): array
    {
        return [
            $this->idColumn,
            $this->parentColumn,
            $this->levelColumn,
            $this->depthColumn,
        ];
    }

    /**
     * Ziskat nazev sloupce s ID
     *
     * @return string
     */
    function getIdColumn(): string
    {
        return $this->idColumn;
    }

    /**
     * Ziskat nazev sloupce s ID rodice
     *
     * @return string
     */
    function getParentColumn(): string
    {
        return $this->parentColumn;
    }

    /**
     * Ziskat nazev sloupce s urovni
     *
     * @return string
     */
    function getLevelColumn(): string
    {
        return $this->levelColumn;
    }

    /**
     * Ziskat nazev sloupce s hloubkou
     *
     * @return string
     */
    function getDepthColumn(): string
    {
        return $this->depthColumn;
    }

    /**
     * Sestavit a provest dotaz na cestu
     *
     * @param array    $columns
     * @param int      $nodeId
     * @param int|null $nodeLevel
     * @return array
     */
    function loadPath(array $columns, int $nodeId, ?int $nodeLevel = null): array
    {
        // zjistit uroven uzlu
        if ($nodeLevel === null) {
            $nodeLevel = $this->getLevel($nodeId);
        }

        // pripravit sloupce
        $columns = array_merge(
            [$this->idColumn, $this->parentColumn, $this->levelColumn, $this->depthColumn], $columns
        );
        $columnCount = count($columns);

        // sestavit dotaz
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

        // nacist uzly
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
     * Ziskat uroven a hloubku daneho uzlu ci korenu
     *
     * @param int|null $nodeId
     * @return array level, depth
     */
    function getLevelAndDepth(?int $nodeId): array
    {
        if ($nodeId === null) {
            // koren
            return [
                0,
                $this->getDepth(null),
            ];
        }

        // uzel
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
     * Ziskat uroven uzlu
     *
     * @param int|null $nodeId
     * @return int
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
     * Ziskat hloubku na danem uzlu ci korenu
     *
     * @param int|null $nodeId
     * @return int|null
     */
    function getDepth(?int $nodeId): ?int
    {
        if ($nodeId === null) {
            $nodeDepth = DB::queryRow('SELECT MAX(' . $this->depthColumn . ') ' . $this->depthColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $this->levelColumn . '=0');
        } else {
            $nodeDepth = DB::queryRow('SELECT ' . $this->depthColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
        }

        if ($nodeDepth === false) {
            // neexistujici node nebo jina chyba
            throw new \RuntimeException(
                ($nodeId === null) ? 'Failed to determine tree depth' : sprintf('Node "%s" does not exist', $nodeId)
            );
        }

        return $nodeDepth[$this->depthColumn] ?? 0;
    }
}
