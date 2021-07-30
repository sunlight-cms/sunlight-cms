<?php

namespace Sunlight\Database;

use Sunlight\Extend;

/**
 * Databazova trida
 *
 * Staticky se pouziva pro praci se sytemovym pripojenim.
 */
class Database
{
    /** @var \mysqli */
    static $mysqli;
    /** @var string */
    static $database;
    /** @var string */
    static $prefix;

    /**
     * Staticka trida
     */
    private function __construct()
    {
    }

    /**
     * Pripojit se k MySQL serveru
     *
     * @param string      $server
     * @param string      $user
     * @param string      $password
     * @param string      $database
     * @param string|null $port
     * @param string|null $charset
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
        $mysqli = @mysqli_connect($server, $user, $password, $database, $port);
        $connectError = mysqli_connect_error();

        if ($connectError !== null) {
            throw new DatabaseException($connectError);
        }

        mysqli_set_charset($mysqli, 'utf8mb4');

        self::$mysqli = $mysqli;
        self::$database = $database;
        self::$prefix = $prefix . '_';
    }

    /**
     * Provest callback v databazove transakci
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
     * Vykonat SQL dotaz
     *
     * @param string $sql
     * @param bool   $expectError nevyhazovat vyjimku pri chybe 1/0
     * @param bool   $log         vyvolat extend udalost 1/0
     * @throws DatabaseException
     * @return \mysqli_result|bool
     */
    static function query(string $sql, bool $expectError = false, bool $log = true)
    {
        if ($log) {
            Extend::call('db.query', ['sql' => $sql]);
        }

        $e = null;
        $result = null;

        try {
            $result = self::$mysqli->query($sql);

            if ($result === false && !$expectError) {
                throw new DatabaseException(sprintf(
                    "%s\n\nSQL: %s",
                    self::$mysqli->error,
                    $sql
                ));
            }
        } catch (\Throwable $e) {
        }

        if ($log) {
            Extend::call('db.query.after', [
                'sql' => $sql,
                'result' => $result,
                'exception' => $e,
            ]);
        }

        if ($e !== null) {
            throw $e;
        }

        return $result;
    }

    /**
     * Vykonat SQL dotaz a vratit prvni radek
     *
     * @param string $sql
     * @param bool   $expectError deaktivovat DBException v pripade chyby
     * @return array|bool
     */
    static function queryRow(string $sql, bool $expectError = false)
    {
        $result = self::query($sql, $expectError);
        if ($result === false) {
            return false;
        }
        $row = self::row($result);
        self::free($result);

        return $row;
    }

    /**
     * Vykonat SQL dotaz a vratit vsechny radky
     *
     * @param string          $sql
     * @param int|string|null $indexBy     indexovat vysledne pole timto klicem z radku
     * @param int|string|null $fetchColumn nacist pouze hodnotu daneho sloupce z kazdeho radku
     * @param bool            $assoc       ziskat kazdy radek jako asociativni pole
     * @param bool            $expectError deaktivovat DBException v pripade chyby
     * @return array[]|bool
     */
    static function queryRows(string $sql, $indexBy = null, $fetchColumn = null, bool $assoc = true, bool $expectError = false)
    {
        $result = self::query($sql, $expectError);
        if ($result === false) {
            return false;
        }
        $rows = self::rows($result, $indexBy, $fetchColumn, $assoc);
        self::free($result);

        return $rows;
    }

    /**
     * Spocitat pocet radku splnujici podminku
     *
     * @param string $table nazev tabulky (bez prefixu)
     * @param string $where podminka
     * @return int
     */
    static function count(string $table, string $where = '1'): int
    {
        $result = self::query('SELECT COUNT(*) FROM ' . self::table($table) . ' WHERE ' . $where);
        if ($result instanceof \mysqli_result) {
            $count = (int) self::result($result);
            self::free($result);

            return $count;
        }

        return 0;
    }

    /**
     * Ziskat nazvy tabulek dle prefixu
     *
     * @param string|null $prefix
     * @return array
     */
    static function getTablesByPrefix(?string $prefix = null): array
    {
        $tables = [];
        $query = self::query('SHOW TABLES LIKE \'' . self::escWildcard($prefix ?? self::$prefix) . '%\'');
        while ($row = self::rown($query)) {
            $tables[] = $row[0];
        }
        self::free($query);

        return $tables;
    }

    /**
     * Ziskat radek z dotazu
     *
     * @param \mysqli_result $result
     * @return array|bool
     */
    static function row(\mysqli_result $result)
    {
        return $result->fetch_assoc() ?? false;
    }

    /**
     * Ziskat vsechny radky z dotazu
     *
     * @param \mysqli_result  $result
     * @param int|string|null $indexBy     indexovat vysledne pole timto klicem z radku
     * @param int|string|null $fetchColumn nacist pouze hodnotu daneho sloupce z kazdeho radku
     * @param bool            $assoc       ziskat kazdy radek jako asociativni pole
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
     * Ziskat radek z dotazu s numerickymi klici namisto nazvu sloupcu
     *
     * @param \mysqli_result $result
     * @return array|bool
     */
    static function rown(\mysqli_result $result)
    {
        return $result->fetch_row() ?? false;
    }

    /**
     * Ziskat konkretni sloupec z prvniho radku vysledku
     *
     * @param \mysqli_result $result
     * @param int            $column cislo sloupce
     * @return mixed
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
     * Ziskat seznam nazvu sloupcu z provedeneho dotazu
     *
     * @param \mysqli_result $result
     * @return array
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
     * Uvolnit vysledek dotazu
     *
     * @param \mysqli_result $result
     * @return bool
     */
    static function free(\mysqli_result $result): bool
    {
        $result->free();

        return true;
    }

    /**
     * Zjistit pocet radku ve vysledku
     *
     * @param \mysqli_result $result
     * @return int
     */
    static function size(\mysqli_result $result): int
    {
        return $result->num_rows;
    }

    /**
     * Zjitit AUTO_INCREMENT ID posledniho vlozeneho radku
     *
     * @return int
     */
    static function insertID(): int
    {
        return self::$mysqli->insert_id;
    }

    /**
     * Zjistit pocet radku ovlivnenych poslednim dotazem
     *
     * @return int
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
     * Zpracovat hodnotu pro pouziti v dotazu
     *
     * @param mixed $value       hodnota
     * @param bool  $handleArray zpracovavat pole 1/0
     * @return string|array|null
     */
    static function esc($value, bool $handleArray = false)
    {
        if ($value === null) {
            return null;
        }
        
        if ($handleArray && is_array($value)) {
            foreach ($value as &$item) {
                $item = self::esc($item);
            }

            return $value;
        }
        if (is_string($value)) {
            return self::$mysqli->real_escape_string($value);
        }
        if (is_numeric($value)) {
            return (0 + $value);
        }

        return self::$mysqli->real_escape_string((string) $value);
    }

    /**
     * Zpracovat hodnotu pro pouziti jako identifikator (nazev tabulky / sloupce) v dotazu
     *
     * @param string $identifier identifikator
     * @return string
     */
    static function escIdt(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Sestavit seznam identifikatoru oddeleny carkami
     *
     * @param array $identifiers
     * @return string
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
     * Escapovat specialni wildcard znaky ("%" a "_") v retezci
     *
     * @param string $string retezec
     * @return string
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
     * Zpracovat hodnotu pro pouziti v dotazu vcetne pripadnych uvozovek
     *
     * @param mixed $value       hodnota
     * @param bool  $handleArray zpracovavat pole 1/0
     * @return string
     */
    static function val($value, bool $handleArray = false): string
    {
        if ($value instanceof RawSqlValue) {
            return $value->getSql();
        }
        $value = self::esc($value, $handleArray);
        if ($handleArray && is_array($value)) {
            $out = '';
            $itemCounter = 0;
            foreach ($value as $item) {
                if ($itemCounter !== 0) {
                    $out .= ',';
                }
                $out .= self::val($item);
                ++$itemCounter;
            }

            return $out;
        }

        if (is_string($value)) {
            return '\'' . $value . '\'';
        }

        if ($value === null) {
            return 'NULL';
        }

        return $value;
    }

    /**
     * Zpracovat hodnotu pro surove pouziti v dotazu
     *
     * @param string $safeSql hodnota
     * @return RawSqlValue
     */
    static function raw(string $safeSql): RawSqlValue
    {
        return new RawSqlValue($safeSql);
    }

    /**
     * Sestavit podminku pro ekvalitu
     *
     * @param mixed $value hodnota
     * @return string "=<hodnota>" nebo "IS NULL"
     */
    static function equal($value): string
    {
        if ($value === null) {
            return 'IS NULL';
        }

        return '=' . self::val($value);
    }

    /**
     * Sestavit podminku pro neekvalitu
     *
     * @param mixed $value hodnota
     * @return string "!=<hodnota>" nebo "IS NOT NULL"
     */
    static function notEqual($value): string
    {
        if ($value === null) {
            return 'IS NOT NULL';
        }

        return '!=' . self::val($value);
    }

    /**
     * Zpracovat pole hodnot pro pouziti v dotazu (napr. IN)
     *
     * @param array $arr pole
     * @return string ve formatu a,b,c,d
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
     * Vlozit radek do databaze
     *
     * @param string $table       nazev tabulky (bez prefixu)
     * @param array  $data        asociativni pole s daty
     * @param bool   $getInsertId vratit insert ID 1/0
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
     * Vlozit vice radku do databaze najednou
     *
     * Priklad:
     *
     * Database::insertMulti('tabulka', array(
     *      array('jmeno' => 'Jan', 'prijmeni' => 'Novak'),
     *      array('jmeno' => 'Pepa', 'prijmeni' => 'Zdepa'),
     * ));
     *
     * Radky nemusi mit sloupce ve stejnem poradi ani jich mit stejny
     * pocet (hodnota sloupce je NULL, neni-li uveden oproti ostatnim radkum)
     *
     * @param string $table nazev tabulky (bez prefixu)
     * @param array  $rows  pole s radky, ktere se maji vlozit (kazdy radek je asociativni pole)
     * @return bool
     */
    static function insertMulti(string $table, array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        // ziskat vsechny uvedene sloupce
        $columns = [];
        foreach ($rows as $row) {
            $columns += array_flip(array_keys($row));
        }
        $columns = array_keys($columns);
        if (empty($columns)) {
            return false;
        }

        // sestavit dotaz
        $sql = "INSERT INTO " . self::table($table) . " (";

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
     * Aktualizovat radky v databazi
     *
     * @param string   $table nazev tabulky (bez prefixu)
     * @param string   $cond  podminka WHERE
     * @param array    $data  asociativni pole se zmenami
     * @param int|null $limit limit upravenych radku (null = bez limitu)
     * @return bool
     */
    static function update(string $table, string $cond, array $data, ?int $limit = 1): bool
    {
        if (empty($data)) {
            return false;
        }
        $counter = 0;
        $set_list = '';
        foreach ($data as $col => $val) {
            if ($counter !== 0) {
                $set_list .= ',';
            }
            $set_list .= self::escIdt($col) . '=' . self::val($val);
            ++$counter;
        }

        return self::query('UPDATE ' . self::table($table) . " SET {$set_list} WHERE {$cond}" . (($limit === null) ? '' : " LIMIT {$limit}"));
    }

    /**
     * Aktualizovat radky v databazi dle seznamu identifikatoru
     *
     * @param string $table       nazev tabulky (bez prefixu)
     * @param string $idColumn    nazev sloupce, ktery obsahuje identifikator
     * @param array  $set         seznam identifikatoru
     * @param array  $changeset   spolecne asociativni pole se zmenami
     * @param int    $maxPerQuery maximalni pocet polozek v 1 dotazu
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
     * Aktualizovat radky v databazi dle mapy zmen pro kazdy radek
     *
     * Pro popis formatu mapy, viz {@see Database::changesetMapToList()}
     *
     * @param string $table        nazev tabulky (bez prefixu)
     * @param string $idColumn     nazev sloupce, ktery obsahuje identifikator
     * @param array  $changesetMap mapa zmen pro kazdy radek: array(id1 => changeset1, ...)
     * @param int    $maxPerQuery  maximalni pocet polozek v 1 dotazu
     */
    static function updateSetMulti(string $table, string $idColumn, array $changesetMap, int $maxPerQuery = 100): void
    {
        foreach (self::changesetMapToList($changesetMap) as $change) {
            self::updateSet($table, $idColumn, $change['set'], $change['changeset'], $maxPerQuery);
        }
    }

    /**
     * Prevest asociativni mapu zmen na seznam zmen
     *
     * Tato metoda slouci spolecne zmeny dohromady.
     *
     * Format mapy zmen:
     *
     *      array(
     *          id1 => array(sloupec1 => novahodnota1, ...),
     *          ...
     *      )
     *
     * @param array $changesetMap
     * @return array[] pole poli s klici "set" a "changeset"
     */
    static function changesetMapToList(array $changesetMap): array
    {
        $commonChanges = [];
        $ids = array_keys($changesetMap);

        foreach ($ids as $id) {
            foreach ($changesetMap[$id] as $column => $value) {
                $commonChanges[$column][$value][$id] = true;
            }
        }

        $changeList = [];

        foreach ($commonChanges as $column => $valueMap) {
            foreach ($valueMap as $value => $idMap) {
                $set = array_keys($idMap);
                $merged = false;

                foreach ($changeList as &$changeItem) {
                    if ($changeItem['set'] === $set) {
                        $changeItem['changeset'][$column] = $value;
                        $merged = true;
                        break;
                    }
                }

                if (!$merged) {
                    $changeList[] = [
                        'set' => $set,
                        'changeset' => [$column => $value],
                    ];
                }
            }
        }

        return $changeList;
    }

    /**
     * Smazat radky v databazi
     *
     * @param string   $table nazev tabulky (bez prefixu)
     * @param string   $cond  podminka WHERE
     * @return bool
     */
    static function delete(string $table, string $cond): bool
    {
        return self::query('DELETE FROM ' . self::table($table) . " WHERE {$cond}");
    }

    /**
     * Smazat radku v databazi dle seznamu identifikatoru
     *
     * @param string $table       nazev tabulky (bez prefixu)
     * @param string $column      nazev sloupce, ktery obsahuje identifikator
     * @param array  $set         seznam identifikatoru
     * @param int    $maxPerQuery maximalni pocet polozek v 1 dotazu
     */
    static function deleteSet(string $table, string $column, array $set, int $maxPerQuery = 100): void
    {
        if (!empty($set)) {
            foreach (array_chunk($set, $maxPerQuery) as $chunk) {
                self::query('DELETE FROM ' . self::table($table) . ' WHERE ' . self::escIdt($column) . ' IN(' . self::arr($chunk) . ')');
            }
        }
    }

    /**
     * Formatovat datum a cas
     *
     * @param int|null $timestamp timestamp (null = time())
     * @return string YY-MM-DD HH:MM:SS (bez uvozovek)
     */
    static function datetime(?int $timestamp = null): string
    {
        return date('Y-m-d H:i:s', $timestamp ?? time());
    }

    /**
     * Formatovat datum
     *
     * @param int|null $timestamp timestamp (null = time())
     * @return string YY-MM-DD (bez uvozovek)
     */
    static function date(?int $timestamp = null): string
    {
        return date('Y-m-d', $timestamp ?? time());
    }
}
