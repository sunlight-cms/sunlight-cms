<?php

namespace Sunlight\Database;

interface TreeFilterInterface
{
    /**
     * Get SQL to filter nodes
     *
     * Example return value: "%__node__%.foo='bar'"
     *
     * @param TreeReader $reader
     * @return string
     */
    public function getNodeSql(TreeReader $reader);

    /**
     * Filter a node at runtime
     *
     * @param array      $node
     * @param TreeReader $reader
     * @return bool
     */
    public function filterNode(array $node, TreeReader $reader);

    /**
     * Choose whether to accept an invalid node which contains a valid child (at any level)
     *
     * @param array      $invalidNode
     * @param array      $validChildNode
     * @param TreeReader $reader
     * @return bool
     */
    public function acceptInvalidNodeWithValidChild(array $invalidNode, array $validChildNode, TreeReader $reader);
}
