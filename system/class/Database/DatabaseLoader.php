<?php

namespace Sunlight\Database;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Regexp;

abstract class DatabaseLoader
{
    /**
     * Remove tables from the database
     */
    static function dropTables(array $tables): void
    {
        foreach ($tables as $table) {
            DB::query('DROP TABLE ' . DB::escIdt($table));
        }
    }

    /**
     * Load database dump
     *
     * @param string|null $currentPrefix prefix that is used in the dump (null = do not replace)
     * @param string|null $newPrefix new prefix (null = do not replace)
     */
    static function load(
        SqlReader $reader,
        ?string $currentPrefix = null,
        ?string $newPrefix = null,
        ?string $currentEngine = null,
        ?string $newEngine = null
    ): void {
        $reader->read(function ($query, $queryMap) use ($currentPrefix, $newPrefix, $currentEngine, $newEngine) {
            if ($currentPrefix !== null && $newPrefix !== null && $currentPrefix !== $newPrefix) {
                $query = self::replacePrefix($query, $queryMap, $currentPrefix, $newPrefix);
            }

            if ($currentEngine !== null && $newEngine !== null && $currentEngine !== $newEngine) {
                $query = self::replaceEngine($query, $queryMap, $currentEngine, $newEngine);
            }

            DB::query($query);
        });
    }

    /**
     * Replace identifier prefixes in the query
     */
    static function replacePrefix(string $query, array $queryMap, string $currentPrefix, string $newPrefix): string
    {
        return Regexp::replace('{`' . preg_quote($currentPrefix) . '([a-zA-Z_]+)`}', $query, function (array $matches, $offset) use ($queryMap, $newPrefix) {
            // determine where we are in the query
            $segment = SqlReader::getQueryMapSegment($queryMap, $offset);

            // replace the match
            if (
                $segment !== null
                && $segment[0] === SqlReader::QUOTED
                && $offset === $segment[1]
            ) {
                // quoted - use new prefix
                return '`' . $newPrefix . $matches[1][0] . '`';
            }

            // comment or other - leave as is
            return $matches[0][0];
        });
    }

    /**
     * Replace engine name in the query
     */
    static function replaceEngine(string $query, array $queryMap, string $currentEngine, string $newEngine): string
    {
        return Regexp::replace('{ENGINE *= *' . preg_quote($currentEngine) . '\b}i', $query, function (array $matches, $offset) use ($queryMap, $newEngine) {
            // replace the match
            if (SqlReader::getQueryMapSegment($queryMap, $offset) === null) {
                // outside quotes or comments - use new engine
                return 'ENGINE=' . $newEngine;
            }

            // quoted or comments - leave as is
            return $matches[0][0];
        });
    }
}
