<?php

namespace Sunlight\Database;

/**
 * Raw SQL value
 *
 * Bypasses {@see DB::esc()}. Use with caution.
 */
class RawSqlValue
{
    /** @var string */
    private $safeSqlString;

    public function __construct($safeSqlString)
    {
        $this->safeSqlString = (string) $safeSqlString;
    }

    public function getSql()
    {
        return $this->safeSqlString;
    }
}
