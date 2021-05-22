<?php

namespace Sunlight\Database;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Regexp;

abstract class DatabaseLoader
{
    /**
     * Remove tables from the database
     *
     * @param array $tables
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
     * @param SqlReader   $reader
     * @param string|null $currentPrefix prefix that is used in the dump (null = do not replace)
     * @param string|null $newPrefix     new prefix (null = do not replace)
     */
    static function load(SqlReader $reader, ?string $currentPrefix = null, ?string $newPrefix = _dbprefix): void
    {
        // determine current sql mode
        $oldSqlMode = DB::queryRow('SHOW VARIABLES WHERE Variable_name=\'sql_mode\'');
        if ($oldSqlMode !== false) {
            $oldSqlMode = $oldSqlMode['Value'];
        } else {
            $oldSqlMode = '';
        }

        // replace sql mode
        DB::query('SET SQL_MODE = \'NO_AUTO_VALUE_ON_ZERO\'');
        
        // restore
        try {
            $reader->read(function ($query, $queryMap) use ($currentPrefix, $newPrefix) {
                if ($currentPrefix !== null && $newPrefix !== null && $currentPrefix !== $newPrefix) {
                    DB::query(DatabaseLoader::replacePrefix($query, $queryMap, $currentPrefix, $newPrefix));
                } else {
                    DB::query($query);
                }
            });
        } catch (\Throwable $e) {
            // restore sql mode in case of an exception
            DB::query('SET SQL_MODE = ' . DB::val($oldSqlMode));

            throw $e;
        }

        // restore sql mode
        DB::query('SET SQL_MODE = ' . DB::val($oldSqlMode));
    }

    /**
     * Replace identifier prefixes in the query
     *
     * @param string $query
     * @param array  $queryMap
     * @param string $currentPrefix
     * @param string $newPrefix
     * @return string
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
