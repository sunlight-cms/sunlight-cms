<?php

namespace Sunlight\Database;

class TreeReaderOptions
{
    /** @var string[] list of additional columns to load */
    public $columns = [];
    /** @var int|null only load this node and its children */
    public $nodeId;
    /** @var int|null node depth, if known (can also be used to limit depth) */
    public $nodeDepth;
    /** @var string|null name of column to use for sorting */
    public $sortBy;
    /** @var bool sort mode */
    public $sortAsc = true;
    /** @var TreeFilterInterface|null tree filter */
    public $filter;
}
