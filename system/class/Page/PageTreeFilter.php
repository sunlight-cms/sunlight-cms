<?php

namespace Sunlight\Page;

use Sunlight\Database\Database as DB;
use Sunlight\Database\TreeFilterInterface;
use Sunlight\Database\TreeReader;

class PageTreeFilter implements TreeFilterInterface
{
    /** @var array */
    protected $options;
    /** @var string */
    protected $sql;

    /**
     * Supported keys in $options:
     * ------------------------------------------------------------
     * ord_start (-)    order from
     * ord_end (-)      order to
     * ord_level (0)    level at which to match the order (0 = root)
     * check_level (1)  check user and page level 1/0
     * check_public (1) check page's public column 1/0
     *
     * @param array $options
     */
    function __construct(array $options)
    {
        // defaults
        $options += [
            'ord_start' => null,
            'ord_end' => null,
            'ord_level' => 0,
            'check_level' => true,
            'check_public' => true,
        ];

        $this->options = $options;
        $this->sql = $this->compileSql($options);
    }

    function filterNode(array $node, TreeReader $reader)
    {
        return
            /* visibility */        $node['visible']
            /* page level */        && (!$this->options['check_level'] || $node['level'] <= _priv_level)
            /* page public */       && (!$this->options['check_public'] || _logged_in || $node['public'])
            /* separator  check */  && $node['type'] != _page_separator
            /* order from */        && (
                                        $this->options['ord_start'] === null
                                        || (
                                            $node['node_level'] != $this->options['ord_level']
                                            || $node['ord'] >= $this->options['ord_start']
                                        )
                                    )
            /* order to */          && (
                                        $this->options['ord_end'] === null
                                        || (
                                            $node['node_level'] != $this->options['ord_level']
                                            || $node['ord'] <= $this->options['ord_end']
                                        )
                                    );
    }

    function acceptInvalidNodeWithValidChild(array $invalidNode, array $validChildNode, TreeReader $reader)
    {
        if (
            ($this->options['ord_start'] !== null || $this->options['ord_end'] !== null)
            && $invalidNode['node_level'] == $this->options['ord_level']
        ) {
            // always reject invalid nodes which have been rejected by order-filtering at that level
            return false;
        }

        return true;
    }

    function getNodeSql(TreeReader $reader)
    {
        return $this->sql;
    }

    /**
     * @param array $options
     * @return string
     */
    protected function compileSql(array $options)
    {
        // base conditions
        $sql = '%__node__%.visible=1 AND %__node__%.type!=' . _page_separator;

        if ($options['check_level']) {
            $sql .= ' AND %__node__%.level<=' . _priv_level;
        }

        // order constraints
        if ($options['ord_start'] !== null || $options['ord_end'] !== null) {
            $ordSql = '';
            if ($options['ord_start'] !== null) {
                $ordSql .= "%__node__%.ord>=" . DB::val($options['ord_start']);
            }
            if ($options['ord_end'] !== null) {
                if ($options['ord_start'] !== null) {
                    $ordSql .= ' AND ';
                }
                $ordSql .= "%__node__%.ord<=" . DB::val($options['ord_end']);
            }

            $sql .= ' AND (%__node__%.node_level!=' . DB::val($options['ord_level']) . ' OR ' . $ordSql . ')';
        }

        return $sql;
    }
}
