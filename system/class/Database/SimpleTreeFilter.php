<?php

namespace Sunlight\Database;

use Sunlight\Database\Database as DB;

/**
 * Simple filter
 *
 * Filters nodes by an associative array.
 */
class SimpleTreeFilter implements TreeFilterInterface
{
    /** @var array */
    private $filter;
    /** @var string */
    private $sql;

    /**
     * Filter example:
     *
     *      array(
     *          column1 => value    // column must be equal to the value
     *          !column2 => value   // column must not be equal to the value
     *          ...
     *      )
     *
     *      This results in the following SQL: column1=value AND column2!=value
     */
    function __construct(array $filter)
    {
        $this->filter = $this->compileFilter($filter);
        $this->sql = $this->compileSql($this->filter);
    }

    function filterNode(array $node, TreeReader $reader): bool
    {
        foreach ($this->filter as $cond) {
            $isInvalid = ($cond[1] === null && $node[$cond[0]] !== null || $node[$cond[0]] != $cond[1]);

            if ($cond[2]) {
                $isInvalid = !$isInvalid;
            }

            if ($isInvalid) {
                return false;
            }
        }

        return true;
    }

    function acceptInvalidNodeWithValidChild(array $invalidNode, array $validChildNode, TreeReader $reader): bool
    {
        return true;
    }

    function getNodeSql(TreeReader $reader): string
    {
        return $this->sql;
    }

    /**
     * Compile a filter array
     *
     * @param array $filter raw filter
     * @throws \InvalidArgumentException on empty filter
     */
    private function compileFilter(array $filter): array
    {
        if (empty($filter)) {
            throw new \InvalidArgumentException('The filter must not be empty');
        }

        $compiledFilter = [];

        foreach ($filter as $prop => $val) {
            if ($prop[0] === '!') {
                $compiledFilter[] = [substr($prop, 1), $val, true];
            } else {
                $compiledFilter[] = [$prop, $val, false];
            }
        }

        return $compiledFilter;
    }

    /**
     * @param array $filter compiled filter
     */
    private function compileSql(array $filter): string
    {
        $sql = '';

        $condCounter = 0;

        foreach ($filter as $cond) {
            if ($condCounter !== 0) {
                $sql .= ' AND ';
            }

            if ($cond[1] !== null) {
                // value
                $sql .= sprintf(
                    '%%__node__%%.`%s`%s=%s',
                    $cond[0],
                    $cond[2] ? '!' : '',
                    DB::val($cond[1])
                );
            } else {
                // null
                $sql .= sprintf(
                    '%%__node__%%.`%s` IS%s NULL',
                    $cond[0],
                    $cond[2] ? ' NOT' : ''
                );
            }

            ++$condCounter;
        }

        return $sql;
    }
}
