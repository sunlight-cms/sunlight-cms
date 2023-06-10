<?php

namespace Sunlight\Admin;

use Sunlight\Database\TreeFilterInterface;
use Sunlight\Database\TreeReader;
use Sunlight\User;

class PageFilter implements TreeFilterInterface
{
    /** @var int|null */
    private $type;
    /** @var bool */
    private $checkPrivilege;

    public function __construct(?int $type = null, bool $checkPrivilege = false)
    {
        $this->type = $type;
        $this->checkPrivilege = $checkPrivilege;
    }

    function filterNode(array $node, TreeReader $reader): bool
    {
        return ($this->type === null || $node['type'] == $this->type)
            && Admin::pageAccess($node, $this->checkPrivilege);
    }

    function acceptInvalidNodeWithValidChild(array $invalidNode, array $validChildNode, TreeReader $reader): bool
    {
        return true;
    }

    function getNodeSql(TreeReader $reader): string
    {
        $sql = '%__node__%.level<=' . User::getLevel();

        if ($this->type !== null) {
            $sql .= ' AND %__node__%.type=' . $this->type;
        }

        return $sql;
    }
}
