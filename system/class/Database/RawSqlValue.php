<?php

namespace Sunlight\Database;

/**
 * Raw SQL value
 *
 * Bypasses {@see Database::val()}. Use with caution.
 */
class RawSqlValue
{
    /** @var string */
    public $sql;

    function __construct(string $sql)
    {
        $this->sql = $sql;
    }
}
