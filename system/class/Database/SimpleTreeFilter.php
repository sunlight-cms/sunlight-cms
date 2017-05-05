<?php

namespace Sunlight\Database;

/**
 * Simple filter
 *
 * Filters nodes by an associative array.
 */
class SimpleTreeFilter implements TreeFilterInterface
{
    /** @var array */
    protected $filter;
    /** @var string */
    protected $sql;

    /**
     * Filter example:
     *
     *      array(
     *          column1 => value    // column must be equal to the value
     *          !column2 => value   // column must not be equal to the value
     *          ...
     *      )
     *
     *      This results in the following SQL: column1=hodnota AND column2!=value
     *
     * @param array $filter
     */
    public function __construct(array $filter)
    {
        $this->filter = $this->compileFilter($filter);
        $this->sql = $this->compileSql($this->filter);
    }

    public function filterNode(array $node, TreeReader $reader)
    {
        foreach ($this->filter as $cond) {
            $isInvalid = (null === $cond[1] && null !== $node[$cond[0]] || $node[$cond[0]] != $cond[1]);

            if ($cond[2]) {
                $isInvalid = !$isInvalid;
            }

            if ($isInvalid) {
                return false;
            }
        }

        return true;
    }

    public function acceptInvalidNodeWithValidChild(array $invalidNode, array $validChildNode, TreeReader $reader)
    {
        return true;
    }

    public function getNodeSql(TreeReader $reader)
    {
        return $this->sql;
    }

    /**
     * Compile an filter array
     *
     * @param array $filter raw filter
     * @throws \InvalidArgumentException on empty filter
     * @return array
     */
    protected function compileFilter(array $filter)
    {
        if (empty($filter)) {
            throw new \InvalidArgumentException('The filter must not be empty');
        }

        $compiledFilter = array();

        foreach ($filter as $prop => $val) {
            if ('!' === $prop[0]) {
                $compiledFilter[] = array(substr($prop, 1), $val, true);
            } else {
                $compiledFilter[] = array($prop, $val, false);
            }
        }

        return $compiledFilter;
    }

    /**
     * @param array $filter compiled filter
     * @return string
     */
    protected function compileSql(array $filter)
    {
        $sql = '';

        $condCounter = 0;
        foreach ($filter as $cond) {
            if (0 !== $condCounter) {
                $sql .= ' AND ';
            }
            if (null !== $cond[1]) {
                // hodnota
                $sql .= sprintf(
                    '%%__node__%%.`%s`%s=%s',
                    $cond[0],
                    $cond[2] ? '!' : '',
                    Database::val($cond[1])
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
