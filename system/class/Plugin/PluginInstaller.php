<?php

namespace Sunlight\Plugin;

use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;

abstract class PluginInstaller
{
    /** @var bool|null */
    private $installed;

    /**
     * See if the plugin is installed
     */
    final function isInstalled(): bool
    {
        if ($this->installed === null) {
            $this->installed = $this->verify();
        }

        return $this->installed;
    }

    /**
     * Install the plugin
     *
     * @throws \LogicException if the plugin is already installed
     */
    final function install(): bool
    {
        if ($this->isInstalled()) {
            throw new \LogicException('The plugin is already installed');
        }

        $this->installed = null;

        DB::transactional(function () {
            $this->doInstall();
        });

        return $this->isInstalled();
    }

    /**
     * Uninstall the plugin
     *
     * @throws \LogicException if the plugin is not installed
     */
    final function uninstall(): bool
    {
        if (!$this->isInstalled()) {
            throw new \LogicException('The plugin is not installed');
        }

        $this->installed = null;

        DB::transactional(function () {
            $this->doUninstall();
        });

        return !$this->isInstalled();
    }

    /**
     * Verify plugin installation status
     *
     * Returns TRUE if the plugin is installed, FALSE otherwise.
     */
    abstract protected function verify(): bool;

    /**
     * Install the plugin
     */
    abstract protected function doInstall(): void;

    /**
     * Uninstall the plugin
     */
    abstract protected function doUninstall(): void;

    /**
     * Check that all given database tables exist
     *
     * @param string[] $tables list of table names (with prefixes)
     * @throws \RuntimeException if only some tables exist
     * @return string[] list of missing tables
     */
    protected function checkTables(array $tables): array
    {
        $foundTables = [];

        foreach ($tables as $table) {
            if (DB::queryRow('SHOW TABLES LIKE ' . DB::val($table)) !== false) {
                $foundTables[] = $table;
            }
        }

        return array_diff($tables, $foundTables);
    }

    /**
     * Check that all given database table columns exist
     *
     * @param string $table table name (with prefix)
     * @param string[] $columns column names
     * @return string[] list of missing columns
     */
    protected function checkColumns(string $table, array $columns): array
    {
        $foundColumns = [];

        foreach ($columns as $column) {
            if (DB::queryRow('SHOW COLUMNS FROM ' . DB::escIdt($table) . ' LIKE ' . DB::val($column)) !== false) {
                $foundColumns[] = $column;
            }
        }

        return array_diff($columns, $foundColumns);
    }

    /**
     * Drop all given database tables
     *
     * @param string[] $tables list of table names (with prefixes)
     */
    protected function dropTables(array $tables): void
    {
        DB::query('DROP TABLE IF EXISTS ' . DB::idtList($tables));
    }

    /**
     * Load a SQL dump
     *
     * @param string $path path to the .sql file
     * @param string|null $currentPrefix prefix that is used in the dump (null = do not replace)
     */
    protected function loadSqlDump(string $path, ?string $currentPrefix = 'sunlight_'): void
    {
        $this->loadSql(SqlReader::fromFile($path), $currentPrefix);
    }

    /**
     * Load a SQL string
     *
     * @param string $sql SQL statements to load
     * @param string|null $currentPrefix prefix that is used in the SQL string (null = do not replace)
     */
    protected function loadSqlString(string $sql, ?string $currentPrefix = 'sunlight_'): void
    {
        $this->loadSql(new SqlReader($sql), $currentPrefix);
    }

    private function loadSql(SqlReader $reader, ?string $currentPrefix = 'sunlight_'): void
    {
        DatabaseLoader::load(
            $reader,
            $currentPrefix,
            $currentPrefix !== null
                ? DB::$prefix
                : null
        );
    }
}
