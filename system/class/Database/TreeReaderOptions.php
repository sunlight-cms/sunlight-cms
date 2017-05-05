<?php

namespace Sunlight\Database;

/**
 * Trida obsahujici moznosti pro nacitani stromu
 */
class TreeReaderOptions
{
    /** @var string[] sloupce, ktera maji byt nacteny (systemove sloupce jsou nacteny vzdy) */
    public $columns = array();
    /** @var int|null nacist pouze tento uzel a jeho potomky */
    public $nodeId = null;
    /** @var int|null hloubka uzlu, je-li znama, pripadne limit hloubky */
    public $nodeDepth = null;
    /** @var string|null nazev sloupce podle ktereho radit nebo NULL */
    public $sortBy = null;
    /** @var bool radit vzestupne */
    public $sortAsc = true;
    /** @var TreeFilterInterface|null filtr stromu  */
    public $filter = null;
}
