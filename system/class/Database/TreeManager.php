<?php

namespace Sunlight\Database;

use Sunlight\Database\Database as DB;

class TreeManager
{
    /** @var string */
    private $table;
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
     * @param string|null $idColumn identifier column name
     * @param string|null $parentColumn parent identifier column name
     * @param string|null $levelColumn level column name
     * @param string|null $depthColumn depth column name
     */
    function __construct(
        string  $table,
        ?string $idColumn = null,
        ?string $parentColumn = null,
        ?string $levelColumn = null,
        ?string $depthColumn = null
    ) {
        $this->table = $table;
        $this->idColumn = $idColumn ??'id';
        $this->parentColumn = $parentColumn ?? 'node_parent';
        $this->levelColumn = $levelColumn ?? 'node_level';
        $this->depthColumn = $depthColumn ?? 'node_depth';
    }

    /**
     * Check if a parent node is valid
     */
    function checkParent(int $nodeId, ?int $parentNodeId): bool
    {
        if (
            $parentNodeId === null
            || $nodeId != $parentNodeId
                && !in_array($parentNodeId, $this->getChildren($nodeId, true))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Create a new node
     */
    function create(array $data, bool $refresh = true): int
    {
        if (array_key_exists($this->levelColumn, $data) || array_key_exists($this->depthColumn, $data)) {
            throw new \InvalidArgumentException(sprintf('Columns "%s" and "%s" cannot be specified manually', $this->levelColumn, $this->depthColumn));
        }

        $data += [
            $this->parentColumn => null,
            $this->levelColumn => 0,
            $this->depthColumn => 0,
        ];

        $nodeId = DB::insert($this->table, $data, true);

        if ($refresh) {
            $this->doRefresh($nodeId);
        }

        return $nodeId;
    }

    /**
     * Update node
     */
    function update(int $nodeId, int $parentNodeId, array $changeset, bool $refresh = true): void
    {
        if (array_key_exists($this->levelColumn, $changeset) || array_key_exists($this->depthColumn, $changeset)) {
            throw new \InvalidArgumentException(sprintf('Columns "%s" and "%s" cannnot be changed manually', $this->levelColumn, $this->depthColumn));
        }

        // check parent
        $hasNewParent = array_key_exists($this->parentColumn, $changeset);

        if ($hasNewParent) {
            $newParent = $changeset[$this->parentColumn];

            if (!$this->checkParent($nodeId, $newParent)) {
                throw new \RuntimeException(sprintf('Node "%s" is not a valid parent node for "%s"', $changeset[$this->parentColumn], $nodeId));
            }
        }

        // update
        DB::update($this->table, $this->idColumn . '=' . DB::val($nodeId), $changeset);

        // refresh
        if ($refresh && $hasNewParent) {
            $this->refreshOnParentUpdate($nodeId, $newParent, $parentNodeId);
        }
    }

    /**
     * Delete node
     */
    function delete(int $nodeId, bool $orphanRemoval = true): void
    {
        if ($orphanRemoval) {
            $children = $this->getChildren($nodeId);
            $this->deleteSet($this->idColumn, $children);
        }

        $rootNodeId = $this->getRoot($nodeId);
        DB::delete($this->table, $this->idColumn . '=' . DB::val($nodeId));

        if ($nodeId != $rootNodeId) {
            $this->doRefreshDepth($rootNodeId, true);
        }
    }

    /**
     * Delete all children of a node
     */
    function purge(int $nodeId): void
    {
        $this->deleteSet($this->idColumn, $this->getChildren($nodeId));
        $this->doRefreshDepth($nodeId);
    }

    /**
     * Refresh tree levels
     */
    function refresh(?int $nodeId = null): void
    {
        $this->doRefresh($nodeId);
    }

    /**
     * Refresh tree levels for a node if a parent has changed
     */
    function refreshOnParentUpdate(int $nodeId, ?int $newParent, ?int $oldParent): void
    {
        if ($oldParent != $newParent) {
            $this->doRefresh($nodeId);
            $this->doRefreshDepth($oldParent);
        }
    }

    /**
     * Remove orphaned nodes
     */
    function purgeOrphaned(bool $refresh = true): void
    {
        do {
            $orphaned = DB::query(
                'SELECT n.' . $this->idColumn . ',n.' . $this->parentColumn
                . ' FROM ' . DB::table($this->table) . ' n'
                . ' LEFT JOIN ' . DB::table($this->table) . ' p ON(n.' . $this->parentColumn . '=p.' . $this->idColumn . ')'
                . ' WHERE n.' . $this->parentColumn . ' IS NOT NULL AND p.id IS NULL'
            );
            $orphanedCount = DB::size($orphaned);

            while ($row = DB::row($orphaned)) {
                // delete children
                $this->deleteSet($this->idColumn, $this->getChildren($row[$this->idColumn], true));

                // delete node nad direct children
                DB::delete($this->table, $this->idColumn . '=' . DB::val($row[$this->idColumn]) . ' OR ' . $this->parentColumn . '=' . DB::val($row[$this->parentColumn]));
            }

        } while ($orphanedCount > 0);

        if ($refresh) {
            $this->doRefresh(null);
        }
    }

    /**
     * Propagate parent node data to children
     *
     * @param mixed $context initial context
     * @param callable $propagator callback(context, current_node), should return a changeset or null
     * @param callable $contextUpdater callback(context, current_node, current_changeset) should return a new context or null
     * @param bool $getChangesetMap only return a changeset map, don't call {@see Database::updateSetMulti()}
     */
    function propagate(array $flatTree, $context, callable $propagator, callable $contextUpdater, bool $getChangesetMap = false): ?array
    {
        $stack = [];
        $contextLevel = 0;
        $changesetMap = [];

        foreach ($flatTree as $node) {
            // update current context
            while (!empty($stack) && $node[$this->levelColumn] < $contextLevel) {
                [$context, $contextLevel] = array_pop($stack);
            }
            
            // call propagator of current context
            $changeset = $propagator($context, $node);

            if ($changeset !== null) {
                $changesetMap[$node[$this->idColumn]] = $changeset;
            }

            // call context updater (for children)
            $newContext = $contextUpdater($context, $node, $changeset);

            if ($newContext !== null) {
                $stack[] = [$context, $contextLevel];
                $context = $newContext;
                $contextLevel = $node[$this->levelColumn] + 1;
                $newContext = null;
            }
        }

        if ($getChangesetMap) {
            return $changesetMap;
        }

        DB::updateSetMulti($this->table, $this->idColumn, $changesetMap);
        return null;
    }

    /**
     * Determine node level
     */
    private function getLevel(?int $nodeId, ?array &$parents = null): int
    {
        $level = 0;
        $parents = [];

        if ($nodeId === null) {
            return 0;
        }

        do {
            $node = DB::queryRow('SELECT ' . $this->parentColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $this->idColumn . '=' . DB::val($nodeId));

            if ($node === false) {
                throw new \RuntimeException(sprintf('Node "%s" does not exist', $nodeId));
            }

            $hasParent = ($node[$this->parentColumn] !== null);

            if ($hasParent) {
                $nodeId = $node[$this->parentColumn];
                $parents[] = $nodeId;

                if (++$level > 200) {
                    throw new \RuntimeException(sprintf('Limit of 200 nesting levels reached near node "%s" - recursive database data?', $node[$this->parentColumn]));
                }
            }
        } while ($hasParent);

        return $level;
    }

    /**
     * Get root node identifier
     */
    private function getRoot(int $nodeId): int
    {
        $parents = [];
        $this->getLevel($nodeId, $parents);

        if (!empty($parents)) {
            return end($parents);
        }

        return $nodeId;
    }

    /**
     * Get an unordered list of all child node identifiers
     */
    private function getChildren(int $nodeId, bool $emptyArrayOnFailure = false): array
    {
        // determine node depth
        $node = DB::queryRow('SELECT ' . $this->depthColumn . ' FROM ' . DB::table($this->table) . ' WHERE id=' . DB::val($nodeId));

        if ($node === false) {
            if ($emptyArrayOnFailure) {
                return [];
            }

            throw new \RuntimeException(sprintf('Node "%s" does not exist', $nodeId));
        }

        if ($node[$this->depthColumn] == 0) {
            // zero depth
            return [];
        }

        // compose query
        $sql = 'SELECT ';

        for ($i = 0; $i < $node[$this->depthColumn]; ++$i) {
            if ($i !== 0) {
                $sql .= ',';
            }

            $sql .= 'n' . $i . '.id';
        }

        $sql .= ' FROM ' . DB::table($this->table) . ' r';
        $parentAlias = 'r';

        for ($i = 0; $i < $node[$this->depthColumn]; ++$i) {
            $nodeAlias = 'n' . $i;
            $sql .= sprintf(
                ' LEFT OUTER JOIN %s %s ON(%2$s.%s=%s.%s)',
                DB::table($this->table),
                $nodeAlias,
                $this->parentColumn,
                $parentAlias,
                $this->idColumn
            );
            $parentAlias = $nodeAlias;
        }

        $sql .= ' WHERE r.' . $this->idColumn . '=' . DB::val($nodeId);

        // load children
        $query = DB::query($sql);
        $childrenMap = [];

        while ($row = DB::rown($query)) {
            for ($i = 0; isset($row[$i]); ++$i) {
                $childrenMap[$row[$i]] = true;
            }
        }

        return array_keys($childrenMap);
    }

    /**
     * Refresh structure data in the given part of the tree
     */
    private function doRefresh(?int $currentNodeId): void
    {
        // determine level and parents of current node
        $currentNodeParents = [];
        $currentNodeLevel = $this->getLevel($currentNodeId, $currentNodeParents);

        // prepare queue and level set
        $queue = [
            [
                $currentNodeId, // node ID
                $currentNodeLevel, // node level
            ],
        ];
        $levelset = [];

        if ($currentNodeId !== null) {
            $levelset[$currentNodeLevel] = [$currentNodeId => true];
        }

        // traverse queue
        for ($i = 0; isset($queue[$i]); ++$i) {
            // traverse children of current node
            if ($queue[$i][0] !== null) {
                $childCondition = $this->parentColumn . '=' . DB::val($queue[$i][0]);
                $childrenLevel = $queue[$i][1] + 1;
            } else {
                $childCondition = $this->parentColumn . ' IS NULL';
                $childrenLevel = 0;
            }

            $children = DB::query('SELECT ' . $this->idColumn . ',' . $this->levelColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $childCondition);

            while ($child = DB::row($children)) {
                if ($childrenLevel != $child[$this->levelColumn]) {
                    if (isset($levelset[$childrenLevel][$child[$this->idColumn]])) {
                        throw new \RuntimeException(sprintf('Recursive dependency on node "%s"', $child[$this->idColumn]));
                    }

                    $levelset[$childrenLevel][$child[$this->idColumn]] = true;
                }

                $queue[] = [$child[$this->idColumn], $childrenLevel];
            }


            unset($children, $queue[$i]);
        }

        // apply level set
        foreach ($levelset as $newLevel => $childrenMap) {
            $this->updateSet($this->idColumn, array_keys($childrenMap), [$this->levelColumn => $newLevel]);
        }

        // update depth of whole branch
        $topNodeId = end($currentNodeParents);

        if ($topNodeId === false) {
            $topNodeId = $currentNodeId;
        }

        $this->doRefreshDepth($topNodeId, true);
    }

    /**
     * Refresh depth data in the given branch
     */
    private function doRefreshDepth(?int $currentNodeId, ?bool $isRootNode = null): void
    {
        // determine root node
        $rootNodeId = $currentNodeId;

        if ($isRootNode !== true && $currentNodeId !== null) {
            $rootNodeId = $this->getRoot($currentNodeId);
        }

        // prepare queue and depth map
        $queue = [
            [
                $rootNodeId, // node id
                0, // node level
                [], // parent list
            ],
        ];
        $depthmap = [];

        // traverse queue
        for ($i = 0; isset($queue[$i]); ++$i) {
            // find children
            if ($queue[$i][0] !== null) {
                $childCondition = $this->parentColumn . '=' . DB::val($queue[$i][0]);
            } else {
                $childCondition = $this->parentColumn . ' IS NULL';
            }

            $children = DB::query('SELECT ' . $this->idColumn . ',' . $this->depthColumn . ' FROM ' . DB::table($this->table) . ' WHERE ' . $childCondition);

            if (DB::size($children) > 0) {
                // node has children, add to queue
                if ($queue[$i][0] !== null) {
                    $childParents = array_merge([$queue[$i][0]], $queue[$i][2]);
                } else {
                    $childParents = [];
                }

                while ($child = DB::row($children)) {
                    $queue[] = [$child[$this->idColumn], $child[$this->depthColumn], $childParents];
                }
            }

            unset($children);

            // update depth of parent nodes
            if ($queue[$i][0] !== null && !isset($depthmap[$queue[$i][0]])) {
                $depthmap[$queue[$i][0]] = 0;
            }

            for ($j = 0; isset($queue[$i][2][$j]); ++$j) {
                $currentDepth = $j + 1;

                if (!isset($depthmap[$queue[$i][2][$j]]) || $depthmap[$queue[$i][2][$j]] < $currentDepth) {
                    $depthmap[$queue[$i][2][$j]] = $currentDepth;
                }
            }

            unset($queue[$i]);
        }

        // convert depth map to sets
        $depthsets = [];

        foreach ($depthmap as $nodeId => $newDepth) {
            $depthsets[$newDepth][] = $nodeId;
        }

        // apply depth sets
        foreach ($depthsets as $newDepth => $nodeIds) {
            $this->updateSet($this->idColumn, $nodeIds, [$this->depthColumn => $newDepth]);
        }
    }

    /**
     * Update set of nodes
     */
    private function updateSet(string $column, array $set, array $changeset): void
    {
        DB::updateSet($this->table, $column, $set, $changeset);
    }

    /**
     * Delete set of nodes
     */
    private function deleteSet(string $column, array $set): void
    {
        DB::deleteSet($this->table, $column, $set);
    }
}
