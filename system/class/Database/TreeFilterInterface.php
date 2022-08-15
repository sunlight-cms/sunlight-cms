<?php

namespace Sunlight\Database;

interface TreeFilterInterface
{
    /**
     * Get SQL to filter nodes
     *
     * Example return value: "%__node__%.foo='bar'"
     */
    function getNodeSql(TreeReader $reader): string;

    /**
     * Filter a node at runtime
     */
    function filterNode(array $node, TreeReader $reader): bool;

    /**
     * Choose whether to accept an invalid node which contains a valid child (at any level)
     */
    function acceptInvalidNodeWithValidChild(array $invalidNode, array $validChildNode, TreeReader $reader): bool;
}
