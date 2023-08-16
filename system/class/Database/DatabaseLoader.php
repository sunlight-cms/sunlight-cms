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
    static function load(SqlReader $reader, ?string $currentPrefix = null, ?string $newPrefix = null): void
    {
        $reader->read(function ($query, $queryMap) use ($currentPrefix, $newPrefix) {
            if ($currentPrefix !== null && $newPrefix !== null && $currentPrefix !== $newPrefix) {
                DB::query(DatabaseLoader::replacePrefix($query, $queryMap, $currentPrefix, $newPrefix));
            } else {
                DB::query($query);
            }
        });
    }

    /**
     * Replace identifier prefixes in the query
     */
    static function replacePrefix(string $query, array $queryMap, string $currentPrefix, string $newPrefix): string
    {
        return Regexp::replace('{`' . preg_quote($currentPrefix) . '([a-zA-Z_]+)`}', $query, function (array $matches, $offset) use ($queryMap, $newPrefix) {
            // determine where we are in the query
            $segment = null;

            for ($i = 0; isset($queryMap[$i]); ++$i) {
                if ($offset >= $queryMap[$i][1] && $offset <= $queryMap[$i][2]) {
                    $segment = $i;
                    break;
                }
            }

            // replace the match
            if (
                $segment !== null
                && $queryMap[$segment][0] === SqlReader::QUOTED
                && $offset === $queryMap[$segment][1]
            ) {
                // quoted - use new prefix
                return '`' . $newPrefix . $matches[1][0] . '`';
            }

            // comment or other - leave as is
            return $matches[0][0];
        });
    }
}
