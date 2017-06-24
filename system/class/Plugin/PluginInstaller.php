<?php

namespace Sunlight\Plugin;

use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;

abstract class PluginInstaller
{
    /** @var bool|null */
    private $installed = null;

    /**
     * Load installer for the given plugin
     *
     * @param string $dir
     * @param string $namespace
     * @param string $camelCasedName
     * @return static
     */
    public static function load($dir, $namespace, $camelCasedName)
    {
        $fileName = "{$dir}/{$camelCasedName}Installer.php";
        $className = "{$namespace}\\{$camelCasedName}Installer";

        include_once $fileName;

        return new $className();
    }

    /**
     * See if the plugin is installed
     *
     * @return bool
     */
    public function isInstalled()
    {
        if ($this->installed === null) {
            $this->installed = (bool) $this->verify();
        }

        return $this->installed;
    }

    /**
     * Install the plugin
     *
     * @throws \LogicException if the plugin is already installed
     * @return bool
     */
    public function install()
    {
        if ($this->isInstalled()) {
            throw new \LogicException('The plugin is already installed');
        }

        $this->installed = null;
        $this->doInstall();

        return $this->isInstalled();
    }

    /**
     * Uninstall the plugin
     *
     * @throws \LogicException if the plugin is not installed
     * @return bool
     */
    public function uninstall()
    {
        if (!$this->isInstalled()) {
            throw new \LogicException('The plugin is not installed');
        }

        $this->installed = null;
        $this->doUninstall();

        return !$this->isInstalled();
    }

    /**
     * Verify plugin installation status
     *
     * Returns TRUE if the plugin is installed, FALSE otherwise.
     *
     * @return bool
     */
    abstract protected function verify();

    /**
     * Install the plugin
     */
    abstract protected function doInstall();

    /**
     * Uninstall the plugin
     */
    abstract protected function doUninstall();

    /**
     * Check that all given database tables exist
     *
     * @param string[] $tables list of table names (with prefixes)
     * @throws \RuntimeException if only some of the tables exist
     * @return string[] list of missing tables
     */
    protected function checkTables(array $tables)
    {
        $foundTables = array();

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
     * @param string   $table   table name (with prefix)
     * @param string[] $columns column names
     * @return string[] list of missing columns
     */
    protected function checkColumns($table, array $columns)
    {
        $foundColumns = array();

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
    protected function dropTables(array $tables)
    {
        DB::query('DROP TABLE IF EXISTS ' . DB::idtList($tables));
    }

    /**
     * Load a SQL dump
     *
     * @param string      $path          path to the .sql file
     * @param string|null $currentPrefix prefix that is used in the dump (null = do not replace)
     * @param string|null $newPrefix     new prefix (null = do not replace)
     */
    protected function loadSqlDump($path, $currentPrefix = 'sunlight_', $newPrefix = _dbprefix)
    {
        DatabaseLoader::load(
            SqlReader::fromFile($path),
            $currentPrefix,
            $newPrefix
        );
    }
}
