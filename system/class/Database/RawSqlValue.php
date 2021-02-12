<?php

namespace Sunlight\Database;

/**
 * Raw SQL value
 *
 * Bypasses {@see Database::esc()}. Use with caution.
 */
class RawSqlValue
{
    /** @var string */
    private $safeSqlString;

    function __construct($safeSqlString)
    {
        $this->safeSqlString = (string) $safeSqlString;
    }

    function getSql(): string
    {
        return $this->safeSqlString;
    }
}
