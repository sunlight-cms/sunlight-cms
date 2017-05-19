<?php

namespace Sunlight\Database;

use Sunlight\Extend;

/**
 * Databazova trida
 *
 * Staticky se pouziva pro praci se sytemovym pripojenim
 */
class Database
{
    /** @var \mysqli|null */
    private static $mysqli;

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
     * @return string|null null on success, error message on failure
     */
    public static function connect($server, $user, $password, $database, $port, $charset = 'utf8')
    {
        $mysqli = @mysqli_connect($server, $user, $password, $database, $port);
        $connectError = mysqli_connect_error();

        if (null === $connectError) {
            if (null !== $charset) {
                mysqli_set_charset($mysqli, $charset);
            }

            static::$mysqli = $mysqli;
        }

        return $connectError;
    }

    /**
     * Ziskat mysqli instanci
     *
     * @return \mysqli|null
     */
    public static function getMysqli()
    {
        return static::$mysqli;
    }

    /**
     * Nastavit mysqli instanci
     *
     * @param \mysqli $mysqli
     */
    public static function setMysqli(\mysqli $mysqli)
    {
        static::$mysqli = $mysqli;
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
    public static function query($sql, $expectError = false, $log = true)
    {
        if ($log) {
            Extend::call('db.query', array('sql' => $sql));
        }

        $e = null;
        $result = null;

        try {
            $result = static::$mysqli->query($sql);

            if ($result === false && !$expectError) {
                throw new DatabaseException(sprintf(
                    "%s\n\nSQL: %s",
                    static::$mysqli->error,
                    $sql
                ));
            }
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        if ($log) {
            Extend::call('db.query.post', array(
                'sql' => $sql,
                'result' => $result,
                'exception' => $e,
            ));
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
    public static function queryRow($sql, $expectError = false)
    {
        $result = static::query($sql, $expectError);
        if (false === $result) {
            return false;
        }
        $row = static::row($result);
        static::free($result);

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
    public static function queryRows($sql, $indexBy = null, $fetchColumn = null, $assoc = true, $expectError = false)
    {
        $result = static::query($sql, $expectError);
        if (false === $result) {
            return false;
        }
        $rows = static::rows($result, $indexBy, $fetchColumn, $assoc);
        static::free($result);

        return $rows;
    }

    /**
     * Spocitat pocet radku splnujici podminku
     *
     * @param string $table nazev tabulky s prefixem
     * @param string $where podminka
     * @return int
     */
    public static function count($table, $where = '1')
    {
        $result = static::query('SELECT COUNT(*) FROM ' . static::escIdt($table) . ' WHERE ' . $where);
        if ($result instanceof \mysqli_result) {
            $count = (int) static::result($result, 0);
            static::free($result);

            return $count;
        }

        return 0;
    }

    /**
     * Ziskat nazvy tabulek dle prefixu
     *
     * @param string $prefix
     * @return array
     */
    public static function getTablesByPrefix($prefix = _dbprefix)
    {
        $tables = array();
        $query = static::query('SHOW TABLES LIKE \'' . static::escWildcard($prefix) . '%\'');
        while ($row = static::rown($query)) {
            $tables[] = $row[0];
        }
        static::free($query);

        return $tables;
    }

    /**
     * Zjistit posledni chybu
     *
     * @return string prazdny retezec pokud neni chyba
     */
    public static function error()
    {
        return static::$mysqli->error;
    }

    /**
     * Ziskat radek z dotazu
     *
     * @param \mysqli_result $result
     * @return array|bool
     */
    public static function row(\mysqli_result $result)
    {
        $row = $result->fetch_assoc();

        if (null !== $row) {
            return $row;
        } else {
            return false;
        }
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
    public static function rows(\mysqli_result $result, $indexBy = null, $fetchColumn = null, $assoc = true)
    {
        $type = $assoc ? MYSQLI_ASSOC : MYSQLI_NUM;
        $rows = array();

        while ($row = $result->fetch_array($type)) {
            if (null !== $indexBy) {
                $rows[$row[$indexBy]] = null !== $fetchColumn ? $row[$fetchColumn] : $row;
            } else {
                $rows[] = null !== $fetchColumn ? $row[$fetchColumn] : $row;
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
    public static function rown(\mysqli_result $result)
    {
        $row = $result->fetch_row();

        if (null !== $row) {
            return $row;
        } else {
            return false;
        }
    }

    /**
     * Ziskat konkretni sloupec z prvniho radku vysledku
     *
     * @param \mysqli_result $result
     * @param int            $column cislo sloupce
     * @return mixed
     */
    public static function result(\mysqli_result $result, $column = 0)
    {
        $row = $result->fetch_row();

        if (null !== $row && isset($row[$column])) {
            return $row[$column];
        } else {
            return null;
        }
    }

    /**
     * Ziskat seznam nazvu sloupcu z provedeneho dotazu
     *
     * @param \mysqli_result $result
     * @return array
     */
    public static function columns(\mysqli_result $result)
    {
        $columns = array();
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
    public static function free(\mysqli_result $result)
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
    public static function size(\mysqli_result $result)
    {
        return $result->num_rows;
    }

    /**
     * Zjitit AUTO_INCREMENT ID posledniho vlozeneho radku
     *
     * @return int
     */
    public static function insertID()
    {
        return static::$mysqli->insert_id;
    }

    /**
     * Zjistit pocet radku ovlivnenych poslednim dotazem
     *
     * @return int
     */
    public static function affectedRows()
    {
        return static::$mysqli->affected_rows;
    }

    /**
     * Zpracovat hodnotu pro pouziti v dotazu
     *
     * @param mixed $value       hodnota
     * @param bool  $handleArray zpracovavat pole 1/0
     * @return string|array|null
     */
    public static function esc($value, $handleArray = false)
    {
        if (null === $value) {
            return null;
        }
        
        if ($handleArray && is_array($value)) {
            foreach ($value as &$item) {
                $item = static::esc($item);
            }

            return $value;
        }
        if (is_string($value)) {
            return static::$mysqli->real_escape_string($value);
        }
        if (is_numeric($value)) {
            return (0 + $value);
        }

        return static::$mysqli->real_escape_string((string) $value);
    }

    /**
     * Zpracovat hodnotu pro pouziti jako identifikator (nazev tabulky / sloupce) v dotazu
     *
     * @param string $identifier identifikator
     * @throws \UnexpectedValueException pokud neni identifikator retezec
     * @return string
     */
    public static function escIdt($identifier)
    {
        if (!is_string($identifier)) {
            throw new \UnexpectedValueException('Invalid identifier type, expected a string');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Sestavit seznam identifikatoru oddeleny carkami
     *
     * @param array $identifiers
     * @return string
     */
    public static function idtList(array $identifiers)
    {
        $sql = '';
        $first = true;
        foreach ($identifiers as $identifier) {
            if ($first) {
                $first = false;
            } else {
                $sql .= ',';
            }
            $sql .= static::escIdt($identifier);
        }

        return $sql;
    }

    /**
     * Escapovat specialni wildcard znaky ("%" a "_") v retezci
     *
     * @param string $string retezec
     * @return string
     */
    public static function escWildcard($string)
    {
        return str_replace(
            array('%', '_'),
            array('\\%', '\\_'),
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
    public static function val($value, $handleArray = false)
    {
        if ($value instanceof RawSqlValue) {
            return $value->getSql();
        }
        $value = static::esc($value, $handleArray);
        if ($handleArray && is_array($value)) {
            $out = '';
            $itemCounter = 0;
            foreach ($value as $item) {
                if (0 !== $itemCounter) {
                    $out .= ',';
                }
                $out .= static::val($item);
                ++$itemCounter;
            }

            return $out;
        } elseif (is_string($value)) {
            return '\'' . $value . '\'';
        } elseif (null === $value) {
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
    public static function raw($safeSql)
    {
        return new RawSqlValue($safeSql);
    }

    /**
     * Sestavit podminku pro ekvalitu
     *
     * @param mixed $value hodnota
     * @return string "=<hodnota>" nebo "IS NULL"
     */
    public static function equal($value)
    {
        if (null === $value) {
            return 'IS NULL';
        } else {
            return '=' . static::val($value);
        }
    }

    /**
     * Sestavit podminku pro neekvalitu
     *
     * @param string $value hodnota
     * @return string "!=<hodnota>" nebo "IS NOT NULL"
     */
    public static function notEqual($value)
    {
        if (null === $value) {
            return 'IS NOT NULL';
        } else {
            return '!=' . static::val($value);
        }
    }

    /**
     * Zpracovat pole hodnot pro pouziti v dotazu (napr. IN)
     *
     * @param array $arr pole
     * @return string ve formatu a,b,c,d
     */
    public static function arr(array $arr)
    {
        $sql = '';

        foreach ($arr as $item) {
            if ('' !== $sql) {
                $sql .= ',';
            }

            $sql .= static::val($item);
        }

        return $sql;
    }

    /**
     * Vlozit radek do databaze
     *
     * @param string $table       nazev tabulky s prefixem
     * @param array  $data        asociativni pole s daty
     * @param bool   $getInsertId vratit insert ID 1/0
     * @return bool|int
     */
    public static function insert($table, array $data, $getInsertId = false)
    {
        if (empty($data)) {
            return false;
        }
        $counter = 0;
        $col_list = '';
        $val_list = '';
        foreach ($data as $col => $val) {
            if (0 !== $counter) {
                $col_list .= ',';
                $val_list .= ',';
            }
            $col_list .= static::escIdt($col);
            $val_list .= static::val($val);
            ++$counter;
        }
        $result = static::query('INSERT INTO ' . static::escIdt($table) . " ({$col_list}) VALUES({$val_list})");
        if (false !== $result && $getInsertId) {
            return static::insertID();
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
     * @param string $table nazev tabulky s prefixem
     * @param array  $rows  pole s radky, ktere se maji vlozit (kazdy radek je asociativni pole)
     * @return bool
     */
    public static function insertMulti($table, array $rows)
    {
        if (empty($rows)) {
            return false;
        }

        // ziskat vsechny uvedene sloupce
        $columns = array();
        foreach ($rows as $row) {
            $columns += array_flip(array_keys($row));
        }
        $columns = array_keys($columns);
        if (empty($columns)) {
            return false;
        }

        // sestavit dotaz
        $sql = "INSERT INTO " . static::escIdt($table) . " (";

        $columnCounter = 0;
        foreach ($columns as $column) {
            if (0 !== $columnCounter) {
                $sql .= ',';
            }
            $sql .= static::escIdt($column);
            ++$columnCounter;
        }

        $sql .= ') VALUES ';

        $rowCounter = 0;
        foreach ($rows as $row) {
            if (0 !== $rowCounter) {
                $sql .= ',';
            }
            $sql .= '(';
            $columnCounter = 0;
            foreach ($columns as $column) {
                if (0 !== $columnCounter) {
                    $sql .= ',';
                }
                $sql .= static::val(isset($row[$column]) ? $row[$column] : null);
                ++$columnCounter;
            }
            $sql .= ')';
            ++$rowCounter;
        }

        return static::query($sql);
    }

    /**
     * Aktualizovat radky v databazi
     *
     * @param string   $table nazev tabulky s prefixem
     * @param string   $cond  podminka WHERE
     * @param array    $data  asociativni pole se zmenami
     * @param int|null $limit limit upravenych radku (null = bez limitu)
     * @return bool
     */
    public static function update($table, $cond, array $data, $limit = 1)
    {
        if (empty($data)) {
            return false;
        }
        $counter = 0;
        $set_list = '';
        foreach ($data as $col => $val) {
            if (0 !== $counter) {
                $set_list .= ',';
            }
            $set_list .= static::escIdt($col) . '=' . static::val($val);
            ++$counter;
        }

        return static::query('UPDATE ' . static::escIdt($table) . " SET {$set_list} WHERE {$cond}" . ((null === $limit) ? '' : " LIMIT {$limit}"));
    }

    /**
     * Aktualizovat radky v databazi dle seznamu identifikatoru
     *
     * @param string $table       nazev tabulky s prefixem
     * @param string $column      nazev sloupce, ktery obsahuje identifikator
     * @param array  $set         seznam identifikatoru
     * @param array  $changeset   spolecne asociativni pole se zmenami
     * @param int    $maxPerQuery maximalni pocet polozek v 1 dotazu
     */
    public static function updateSet($table, $column, array $set, $changeset, $maxPerQuery = 100)
    {
        if (!empty($set)) {
            foreach (array_chunk($set, $maxPerQuery) as $chunk) {
                static::update(
                    $table,
                    static::escIdt($column) . ' IN(' . static::arr($chunk) . ')',
                    $changeset,
                    null
                );
            }
        }
    }

    /**
     * Aktualizovat radky v databazi dle mapy zmen pro kazdy radek
     *
     * Pro popis formatu mapy, viz {@see Database::changesetMapToList}
     *
     * @param string $table        nazev tabulky s prefixem
     * @param string $column       nazev sloupce, ktery obsahuje identifikator
     * @param array  $changesetMap mapa zmen pro kazdy radek
     * @param int    $maxPerQuery  maximalni pocet polozek v 1 dotazu
     */
    public static function updateSetMulti($table, $column, array $changesetMap, $maxPerQuery = 100)
    {
        foreach (static::changesetMapToList($changesetMap) as $change) {
            static::updateSet($table, $column, $change['set'], $change['changeset'], $maxPerQuery);
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
    public static function changesetMapToList(array $changesetMap)
    {
        $commonChanges = array();
        $ids = array_keys($changesetMap);

        foreach ($ids as $id) {
            foreach ($changesetMap[$id] as $column => $value) {
                $commonChanges[$column][$value][$id] = true;
            }
        }

        $changeList = array();

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
                    $changeList[] = array(
                        'set' => $set,
                        'changeset' => array($column => $value),
                    );
                }
            }
        }

        return $changeList;
    }

    /**
     * Smazat radky v databazi
     *
     * @param string   $table nazev tabulky s prefixem
     * @param string   $cond  podminka WHERE
     * @param int|null $limit limit smazanych radku (null = bez limitu)
     * @return bool
     */
    public static function delete($table, $cond, $limit = 1)
    {
        return static::query('DELETE FROM ' . static::escIdt($table) . " WHERE {$cond}" . ((null === $limit) ? '' : " LIMIT {$limit}"));
    }

    /**
     * Smazat radku v databazi dle seznamu identifikatoru
     *
     * @param string $table       nazev tabulky s prefixem
     * @param string $column      nazev sloupce, ktery obsahuje identifikator
     * @param array  $set         seznam identifikatoru
     * @param int    $maxPerQuery maximalni pocet polozek v 1 dotazu
     */
    public static function deleteSet($table, $column, array $set, $maxPerQuery = 100)
    {
        if (!empty($set)) {
            foreach (array_chunk($set, $maxPerQuery) as $chunk) {
                static::query('DELETE FROM ' . static::escIdt($table) . ' WHERE ' . static::escIdt($column) . ' IN(' . static::arr($chunk) . ')');
            }
        }
    }

    /**
     * Formatovat datum a cas
     *
     * @param int $timestamp timestamp
     * @return string YY-MM-DD HH:MM:SS (bez uvozovek)
     */
    public static function datetime($timestamp)
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Formatovat datum
     *
     * @param int $timestamp timestamp
     * @return string YY-MM-DD (bez uvozovek)
     */
    public static function date($timestamp)
    {
        return date('Y-m-d', $timestamp);
    }
}
