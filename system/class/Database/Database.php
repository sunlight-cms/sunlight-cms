<?php

namespace Sunlight\Database;

use Sunlight\Extend;

/**
 * Static database class
 * 
 * Manages acccess to the system database.
 */
abstract class Database
{
    /** @var \mysqli */
    static $mysqli;
    /** @var string */
    static $database;
    /** @var string */
    static $prefix;

    /**
     * Connect to a MySQL server
     *
     * @throws DatabaseException on failure
     */
    static function connect(
        string $server,
        string $user,
        string $password,
        string $database,
        ?string $port,
        string $prefix
    ): void {
        if (PHP_VERSION_ID < 80100) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }

        try {
            $mysqli = mysqli_connect($server, $user, $password, $database, $port);
        } catch (\mysqli_sql_exception $e) {
            throw new DatabaseException('Could not connect to the database', 0, $e);
        }

        mysqli_set_charset($mysqli, 'utf8mb4');

        self::$mysqli = $mysqli;
        self::$database = $database;
        self::$prefix = $prefix . '_';
    }

    /**
     * Execute a callback in a transaction
     * 
     * @throws DatabaseException on failure
     */
    static function transactional(callable $callback): void
    {
        static $inTransaction = false;

        if ($inTransaction) {
            throw new DatabaseException('Already in a transaction');
        }

        if (!self::$mysqli->begin_transaction()) {
            throw new DatabaseException('Could not begin a transaction');
        }

        try {
            $callback();

            if (!self::$mysqli->commit()) {
                throw new DatabaseException('Could not commit the transaction');
            }
        } catch (\Throwable $e) {
            if (!self::$mysqli->rollback()) {
                throw new DatabaseException('Could not rollback the transaction', 0, $e);
            }

            throw $e;
        } finally {
            $inTransaction = false;
        }
    }

    /**
     * Run a SQL query
     *
     * @param bool $expectError don't throw an exception on failure 1/0
     * @param bool $event trigger an extend event 1/0
     * @throws DatabaseException
     * @return \mysqli_result|false
     */
    static function query(string $sql, bool $expectError = false, bool $event = true)
    {
        if ($event) {
            Extend::call('db.query', ['sql' => $sql]);
        }

        try {
            return self::$mysqli->query($sql);
        } catch (\mysqli_sql_exception $e) {
            if ($expectError) {
                return false;
            }

            throw new DatabaseException(sprintf("%s\n\nSQL: %s", $e->getMessage(), $sql), 0, $e);
        } finally {
            if ($event) {
                Extend::call('db.query.after', ['sql' => $sql]);
            }
        }
    }

    /**
     * Run a SQL query and return the first result
     *
     * @param bool $expectError don't throw an exception on failure 1/0
     * @return array|false
     */
    static function queryRow(string $sql, bool $expectError = false)
    {
        $result = self::query($sql, $expectError);

        if ($result === false) {
            return false;
        }

        return self::row($result);
    }

    /**
     * Run a SQL query and return all rows
     *
     * @param int|string|null $indexBy index the resulting array using the given column
     * @param int|string|null $fetchColumn only fetch the given column instead of the entire row
     * @param bool $assoc fetch rows as associative arrays 1/0
     * @param bool $expectError don't throw an exception on failure 1/0
     * @return array[]|false
     */
    static function queryRows(string $sql, $indexBy = null, $fetchColumn = null, bool $assoc = true, bool $expectError = false)
    {
        $result = self::query($sql, $expectError);

        if ($result === false) {
            return false;
        }

        return self::rows($result, $indexBy, $fetchColumn, $assoc);
    }

    /**
     * Count number of rows in a table using a condition
     *
     * @param string $table table name (no prefix)
     */
    static function count(string $table, string $where = '1'): int
    {
        $result = self::query('SELECT COUNT(*) FROM ' . self::table($table) . ' WHERE ' . $where);

        if ($result instanceof \mysqli_result) {
            return (int) self::result($result);
        }

        return 0;
    }

    /**
     * List table names by common prefix
     *
     * Uses system prefix if none is given.
     *
     * @return string[]
     */
    static function getTablesByPrefix(?string $prefix = null): array
    {
        $tables = [];
        $query = self::query('SHOW TABLES LIKE \'' . self::escWildcard($prefix ?? self::$prefix) . '%\'');

        while ($row = self::rown($query)) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    /**
     * Get a single row from a result
     *
     * @return array|false
     */
    static function row(\mysqli_result $result)
    {
        return $result->fetch_assoc() ?? false;
    }

    /**
     * Get all rows from a result
     *
     * @param int|string|null $indexBy index the resulting array using the given column
     * @param int|string|null $fetchColumn only fetch the given column instead of the entire row
     * @param bool $assoc fetch rows as assoiative arrays 1/0
     * @return array[]
     */
    static function rows(\mysqli_result $result, $indexBy = null, $fetchColumn = null, bool $assoc = true): array
    {
        $type = $assoc ? MYSQLI_ASSOC : MYSQLI_NUM;
        $rows = [];

        while ($row = $result->fetch_array($type)) {
            if ($indexBy !== null) {
                $rows[$row[$indexBy]] = $fetchColumn !== null ? $row[$fetchColumn] : $row;
            } else {
                $rows[] = $fetchColumn !== null ? $row[$fetchColumn] : $row;
            }
        }

        return $rows;
    }

    /**
     * Get a single row from a result using numeric indexes
     *
     * @return array|false
     */
    static function rown(\mysqli_result $result)
    {
        return $result->fetch_row() ?? false;
    }

    /**
     * Get a single column from the first result
     */
    static function result(\mysqli_result $result, int $column = 0)
    {
        $row = $result->fetch_row();

        if ($row !== null && isset($row[$column])) {
            return $row[$column];
        }

        return null;
    }

    /**
     * Get a list of columns in the given result
     */
    static function columns(\mysqli_result $result): array
    {
        $columns = [];
        $fields = $result->fetch_fields();

        for ($i = 0; isset($fields[$i]); ++$i) {
            $columns[] = $fields[$i]->name;
        }

        return $columns;
    }

    /**
     * Get number of rows in a result
     */
    static function size(\mysqli_result $result): int
    {
        return $result->num_rows;
    }

    /**
     * Get AUTO_INCREMENT ID of last inserted row
     */
    static function insertID(): int
    {
        return self::$mysqli->insert_id;
    }

    /**
     * Get number of rows affected by the last query
     */
    static function affectedRows(): int
    {
        return self::$mysqli->affected_rows;
    }

    /**
     * Get prefixed table name
     */
    static function table(string $name): string
    {
        return Extend::fetch('db.table', ['name' => $name]) ?? self::$prefix . $name;
    }

    /**
     * Escape a string for use in a query
     *
     * This function does not add quotes - {@see Database::val()}.
     */
    static function esc(string $value): string
    {
        return self::$mysqli->real_escape_string($value);
    }

    /**
     * Escape a value to be used as an identifier (table or column name)
     */
    static function escIdt(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Compose a list of identifiers separated by commas
     */
    static function idtList(array $identifiers): string
    {
        $sql = '';
        $first = true;

        foreach ($identifiers as $identifier) {
            if ($first) {
                $first = false;
            } else {
                $sql .= ',';
            }

            $sql .= self::escIdt($identifier);
        }

        return $sql;
    }

    /**
     * Escape special wildcard characters in a string ("%" and "_")
     */
    static function escWildcard(string $string): string
    {
        return str_replace(
            ['%', '_'],
            ['\\%', '\\_'],
            $string
        );
    }

    /**
     * Format a value to be used in a query, including quotes if necessary
     */
    static function val($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof RawSqlValue) {
            return $value->getSql();
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            $value = (0 + $value);

            if (is_int($value)) {
                return sprintf('%d', $value);
            }

            return sprintf('%.14F', $value);
        }

        return '\'' . self::esc((string) $value) . '\'';
    }

    /**
     * Create a RAW sql value that will be ignored by {@see Database::val()}
     */
    static function raw(string $safeSql): RawSqlValue
    {
        return new RawSqlValue($safeSql);
    }

    /**
     * Create an equality condition
     *
     * @return string "=<value>" or "IS NULL"
     */
    static function equal($value): string
    {
        if ($value === null) {
            return 'IS NULL';
        }

        return '=' . self::val($value);
    }

    /**
     * Create a non-equality condition
     *
     * @return string "!=<value>" or "IS NOT NULL"
     */
    static function notEqual($value): string
    {
        if ($value === null) {
            return 'IS NOT NULL';
        }

        return '!=' . self::val($value);
    }

    /**
     * Format an array of values as a list of items separated by commas
     */
    static function arr(array $arr): string
    {
        $sql = '';

        foreach ($arr as $item) {
            if ($sql !== '') {
                $sql .= ',';
            }

            $sql .= self::val($item);
        }

        return $sql;
    }

    /**
     * Insert a row
     *
     * @param string $table table name (no prefix)
     * @param array<string, mixed> $data associative array with row data
     * @param bool $getInsertId return AUTO_INCREMENT ID 1/0
     * @return bool|int
     */
    static function insert(string $table, array $data, bool $getInsertId = false)
    {
        if (empty($data)) {
            return false;
        }

        $counter = 0;
        $col_list = '';
        $val_list = '';

        foreach ($data as $col => $val) {
            if ($counter !== 0) {
                $col_list .= ',';
                $val_list .= ',';
            }

            $col_list .= self::escIdt($col);
            $val_list .= self::val($val);
            ++$counter;
        }

        $result = self::query('INSERT INTO ' . self::table($table) . " ({$col_list}) VALUES({$val_list})");

        if ($result !== false && $getInsertId) {
            return self::insertID();
        }
        
        return $result;
    }

    /**
     * Insert multiple rows
     *
     * If a column is missing in any of the rows, NULL will be used instead.
     *
     * @param string $table table name (no prefix)
     * @param array<array<string, mixed>> $rows list of associative arrays (rows) to insert
     */
    static function insertMulti(string $table, array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        // get all columns
        $columns = [];

        foreach ($rows as $row) {
            $columns += array_flip(array_keys($row));
        }

        $columns = array_keys($columns);

        if (empty($columns)) {
            return false;
        }

        // compose query
        $sql = 'INSERT INTO ' . self::table($table) . ' (';

        $columnCounter = 0;

        foreach ($columns as $column) {
            if ($columnCounter !== 0) {
                $sql .= ',';
            }

            $sql .= self::escIdt($column);
            ++$columnCounter;
        }

        $sql .= ') VALUES ';

        $rowCounter = 0;

        foreach ($rows as $row) {
            if ($rowCounter !== 0) {
                $sql .= ',';
            }

            $sql .= '(';
            $columnCounter = 0;

            foreach ($columns as $column) {
                if ($columnCounter !== 0) {
                    $sql .= ',';
                }

                $sql .= self::val($row[$column] ?? null);
                ++$columnCounter;
            }

            $sql .= ')';
            ++$rowCounter;
        }

        return self::query($sql);
    }

    /**
     * Update rows
     *
     * @param string $table table name (no prefix)
     * @param string $cond WHERE condition
     * @param array<string, mixed> $changeset associative array with changes
     * @param int|null $limit max number of updated rows (null = no limit)
     */
    static function update(string $table, string $cond, array $changeset, ?int $limit = 1): bool
    {
        if (empty($changeset)) {
            return false;
        }

        $counter = 0;
        $set_list = '';

        foreach ($changeset as $col => $val) {
            if ($counter !== 0) {
                $set_list .= ',';
            }

            $set_list .= self::escIdt($col) . '=' . self::val($val);
            ++$counter;
        }

        return self::query('UPDATE ' . self::table($table) . " SET {$set_list} WHERE {$cond}" . (($limit === null) ? '' : " LIMIT {$limit}"));
    }

    /**
     * Update rows using a list of identifiers
     *
     * @param string $table table name (no prefix)
     * @param string $idColumn identifier column name
     * @param scalar[] $set list of identifiers
     * @param array<string, mixed> $changeset associative array with changes for all rows
     * @param int $maxPerQuery max number of identifiers per query
     */
    static function updateSet(string $table, string $idColumn, array $set, array $changeset, int $maxPerQuery = 100): void
    {
        if (!empty($set)) {
            foreach (array_chunk($set, $maxPerQuery) as $chunk) {
                self::update(
                    $table,
                    self::escIdt($idColumn) . ' IN(' . self::arr($chunk) . ')',
                    $changeset,
                    null
                );
            }
        }
    }

    /**
     * Update rows using a map of changes
     *
     * @param string $table table name (no prefix)
     * @param string $idColumn identifier column name
     * @param array<scalar, array<string, mixed>> $changesetMap map of identifiers to changesets: array(id1 => changeset1, ...)
     * @param int $maxPerQuery max number of identifiers per query
     */
    static function updateSetMulti(string $table, string $idColumn, array $changesetMap, int $maxPerQuery = 100): void
    {
        foreach (self::changesetMapToList($changesetMap) as $change) {
            self::updateSet($table, $idColumn, $change['set'], $change['changeset'], $maxPerQuery);
        }
    }

    /**
     * Convert a changeset map to a list of common update sets
     *
     * @param array<scalar, array<string, mixed>> $changesetMap array(id1 => changeset1, ...)
     * @return array<array{set: array, changeset: array}>
     */
    static function changesetMapToList(array $changesetMap): array
    {
        $commonChanges = [];
        $ids = array_keys($changesetMap);

        foreach ($ids as $id) {
            foreach ($changesetMap[$id] as $column => $value) {
                $commonChanges[$column][self::val($value)][$id] = true;
            }
        }

        $changeList = [];

        foreach ($commonChanges as $column => $valueMap) {
            foreach ($valueMap as $value => $idMap) {
                $set = array_keys($idMap);
                $merged = false;

                foreach ($changeList as &$changeItem) {
                    if ($changeItem['set'] === $set) {
                        $changeItem['changeset'][$column] = self::raw($value);
                        $merged = true;
                        break;
                    }
                }

                if (!$merged) {
                    $changeList[] = [
                        'set' => $set,
                        'changeset' => [$column => self::raw($value)],
                    ];
                }
            }
        }

        return $changeList;
    }

    /**
     * Delete rows
     *
     * @param string $table table name (no prefix)
     * @param string $cond WHERE condition
     */
    static function delete(string $table, string $cond): bool
    {
        return self::query('DELETE FROM ' . self::table($table) . " WHERE {$cond}");
    }

    /**
     * Delete rows using a list of identifiers
     *
     * @param string $table table name (no prefix)
     * @param string $idColumn identifier column name
     * @param scalar[] $set list of identifiers
     * @param int $maxPerQuery max number of identifiers per query
     */
    static function deleteSet(string $table, string $idColumn, array $set, int $maxPerQuery = 100): void
    {
        if (!empty($set)) {
            foreach (array_chunk($set, $maxPerQuery) as $chunk) {
                self::query('DELETE FROM ' . self::table($table) . ' WHERE ' . self::escIdt($idColumn) . ' IN(' . self::arr($chunk) . ')');
            }
        }
    }

    /**
     * Format date and time
     *
     * @param int|null $timestamp timestamp or null (= current time)
     * @return string YY-MM-DD HH:MM:SS (no quotes)
     */
    static function datetime(?int $timestamp = null): string
    {
        return date('Y-m-d H:i:s', $timestamp ?? time());
    }

    /**
     * Format date
     *
     * @param int|null $timestamp timestamp or null (= current date)
     * @return string YY-MM-DD (no quotes)
     */
    static function date(?int $timestamp = null): string
    {
        return date('Y-m-d', $timestamp ?? time());
    }
}
