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
    static function dropTables(array $tables)
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
    static function load(SqlReader $reader, $currentPrefix = null, $newPrefix = _dbprefix)
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
        } catch (\Exception $e) {
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
    static function replacePrefix($query, array $queryMap, $currentPrefix, $newPrefix)
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
                && SqlReader::QUOTED === $queryMap[$segment][0]
                && $offset === $queryMap[$segment][1]
            ) {
                // quoted - use new prefix
                return '`' . $newPrefix . $matches[1][0] . '`';
            } else {
                // comment or other - leave as is
                return $matches[0][0];
            }
        });
    }
}
