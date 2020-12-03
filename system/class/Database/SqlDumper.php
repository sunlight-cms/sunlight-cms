<?php

namespace Sunlight\Database;

use Kuria\Cache\Util\TemporaryFile;
use Sunlight\Database\Database as DB;
use Sunlight\Util\Filesystem;

/**
 * Database dumper
 *
 * Dumps given tables and/or rows to a temporary SQL dump file.
 */
class SqlDumper
{
    /** @var array */
    protected $tables = [];
    /** @var bool */
    protected $dumpData = true;
    /** @var bool */
    protected $dumpTables = true;
    /** @var int|null */
    protected $maxPacketSize;

    /**
     * Dump tables and/or data
     *
     * @throws DatabaseException on failure
     * @return TemporaryFile
     */
    function dump()
    {
        $tmpFile = Filesystem::createTmpFile();
        $handle = null;

        try {
            $handle = fopen($tmpFile, 'wb');

            if ($this->dumpTables) {
                $this->dumpTables($handle);
            }

            if ($this->dumpData) {
                $this->dumpData($handle);
            }

            fclose($handle);
        } catch (\Exception $e) {
            if ($handle !== null) {
                fclose($handle);
            }
            $tmpFile->discard();

            throw $e;
        }

        return $tmpFile;
    }

    /**
     * Add table to dump
     *
     * @param string $table
     * @return $this
     */
    function addTable($table)
    {
        $this->tables[] = $table;

        return $this;
    }

    /**
     * Add tables to dump
     *
     * @param string[] $tables
     * @return $this
     */
    function addTables(array $tables)
    {
        foreach ($tables as $table) {
            $this->tables[] = $table;
        }

        return $this;
    }

    /**
     * Set whether data should be dumped
     *
     * @param bool $dumpData
     * @return $this
     */
    function setDumpData($dumpData)
    {
        $this->dumpData = $dumpData;

        return $this;
    }

    /**
     * Set whether table definitions should be dumped
     *
     * @param bool $dumpTables
     * @return $this
     */
    function setDumpTables($dumpTables)
    {
        $this->dumpTables = $dumpTables;

        return $this;
    }

    /**
     * Get max packet size
     *
     * @return int
     */
    function getMaxPacketSize()
    {
        if ($this->maxPacketSize === null) {
            // determine max packet size
            $maxAllowedPacket = DB::queryRow('SHOW VARIABLES WHERE Variable_name=\'max_allowed_packet\'');
            if ($maxAllowedPacket === false) {
                throw new DatabaseException('Could not determine value of the "max_allowed_packet" variable');
            }

            // use 16MB or the server's value, if smaller
            $this->maxPacketSize = min((int) $maxAllowedPacket['Value'], 16777216);
        }

        return $this->maxPacketSize;
    }

    /**
     * Set max packet size
     *
     * @param int|null $maxPacketSize
     * @return $this
     */
    function setMaxPacketSize($maxPacketSize)
    {
        $this->maxPacketSize = $maxPacketSize;

        return $this;
    }

    /**
     * Dump tables
     *
     * @param resource $handle
     */
    protected function dumpTables($handle)
    {
        foreach ($this->tables as $table) {
            $createTable = DB::queryRow('SHOW CREATE TABLE `' . $table . '`');

            if ($createTable === false || !isset($createTable['Create Table'])) {
                throw new DatabaseException(sprintf('SHOW CREATE TABLE failed for "%s"', $table));
            }

            fwrite($handle, $createTable['Create Table']);
            fwrite($handle, ";\n");
        }
    }

    /**
     * Dump data
     *
     * @param resource $handle
     */
    protected function dumpData($handle)
    {
        foreach ($this->tables as $table) {
            $columns = $this->getTableColumns($table);
            $result = DB::query('SELECT * FROM `' . $table . '`');

            $this->dumpTableData($handle, $table, $columns, $result);

            DB::free($result);
        }
    }

    /**
     * Dump table data
     *
     * @param resource $handle
     * @param string   $table
     * @param array    $columns
     * @param mixed    $result
     */
    protected function dumpTableData($handle, $table, array $columns, $result)
    {
        $columnList = DB::idtList(array_keys($columns));
        $insertStatement = 'INSERT INTO `' . $table . '` (' . $columnList . ') VALUES ';
        $insertStatementSize = strlen($insertStatement);

        $currentQuerySize = 0;
        $maxPacketSize = $this->getMaxPacketSize();
        $writtenInsertSyntax = false;
        $isFirstRowStatement = false;
        while ($rowx = DB::row($result)) {

            // write initial insert statement
            if (!$writtenInsertSyntax) {
                $currentQuerySize += fwrite($handle, $insertStatement);
                $writtenInsertSyntax = true;
                $isFirstRowStatement = true;
            }

            // compose row
            $rowStatement = '(';
            $isFirstColumn = true;
            foreach ($columns as $column => $columnOptions) {
                // get value
                if (key_exists($column, $rowx)) {
                    $value = $rowx[$column];
                } else {
                    $value = $columnOptions[1];
                }

                // cast
                if ($value !== null) {
                    switch ($columnOptions[0]) {
                        case 'integer':
                            $value = (int) $value;
                            break;
                        case 'string':
                            $value = (string) $value;
                            break;
                        default:
                            throw new \LogicException(sprintf('Invalid column type "%s"', $columnOptions[0]));
                    }
                }

                // add to row
                if ($isFirstColumn) {
                    $isFirstColumn = false;
                } else {
                    $rowStatement .= ',';
                }
                $rowStatement .= DB::val($value);
            }
            $rowStatement .= ')';

            // check row statement size
            $rowStatementSize = strlen($rowStatement);
            $requiredBytes = $rowStatementSize + ($isFirstRowStatement ? 0 : 1);
            if ($currentQuerySize + $requiredBytes > $maxPacketSize) {
                // not enough bytes left
                if ($isFirstRowStatement || $insertStatementSize + $rowStatementSize > $currentQuerySize) {
                    // impossible to fit
                    throw new DatabaseException(sprintf(
                        'Encountered row in table "%s" that is too big for maximum packet size of %d bytes',
                        $table,
                        $maxPacketSize
                    ));
                }

                // start new insert statement
                fwrite($handle, ";\n");
                $currentQuerySize = fwrite($handle, $insertStatement);
                $isFirstRowStatement = true;
            }

            // write row
            if ($isFirstRowStatement) {
                $isFirstRowStatement = false;
            } else {
                $currentQuerySize += fwrite($handle, ',');
            }
            $currentQuerySize += fwrite($handle, $rowStatement);
        }

        // close existing insert statement
        if ($writtenInsertSyntax) {
            fwrite($handle, ";\n");
        }
    }

    /**
     * Get table columns
     *
     * @param string $table
     * @return array
     */
    protected function getTableColumns($table)
    {
        $columns = [];
        $result = DB::query('SHOW COLUMNS FROM `' . $table . '`');

        while ($row = DB::row($result)) {
            if (($parentPos = strpos($row['Type'], '(')) !== false) {
                $type = substr($row['Type'], 0, $parentPos);
            } else {
                $type = $row['Type'];
            }

            switch (strtolower($type)) {
                case 'integer':
                case 'int':
                    $type = 'integer';
                    break;
                default:
                    $type = 'string';
                    break;
            }

            $columns[$row['Field']] = [$type, $row['Default']];
        }

        DB::free($result);

        return $columns;
    }
}
