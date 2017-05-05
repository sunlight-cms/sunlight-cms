<?php

namespace Sunlight\Backup;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;
use Sunlight\Util\Filesystem;

class BackupRestorer
{
    /** @var Backup */
    protected $backup;

    /**
     * @param Backup $backup
     */
    public function __construct(Backup $backup)
    {
        $this->backup = $backup;
    }

    /**
     * Validate the backup
     *
     * @param array|null &$errors
     * @return bool
     */
    public function validate(&$errors = null)
    {
        $errors = array();

        $this->backup->validateMetaData(
            $this->backup->getMetaData(),
            $errors
        );

        return empty($errors);
    }

    /**
     * Restore the backup
     *
     * @param bool       $database    restore the database 1/0
     * @param array      $directories directory paths to restore (from backup's metadata), null = all
     * @param array|null &$errors
     * @return bool
     */
    public function restore($database, array $directories = null, &$errors = null)
    {
        $errors = array();

        // normalize arguments
        $database = $database && $this->backup->hasDatabaseDump();

        if (null === $directories) {
            $directories = $this->backup->getMetaData('directory_list');
        } else {
            $directories = array_intersect($this->backup->getMetaData('directory_list'), $directories);
        }

        // verify what we are restoring
        if (!$database && empty($directories)) {
            $errors[] = 'nothing to restore';
        } else {
            // verify directories
            foreach ($directories as $directory) {
                if (!Filesystem::checkDirectory(_root . $directory, true, $failedPaths)) {
                    $failedPathsString = implode(', ', array_slice($failedPaths, 0, 3));
                    if (sizeof($failedPaths) > 3) {
                        $failedPathsString .= sprintf(' and %d more', sizeof($failedPaths) - 3);
                    }

                    $errors[] = sprintf('cannot write to "%s", please check privileges (%s)', $failedPathsString);
                }
            }
        }

        // restore
        if (empty($errors)) {
            // load database
            if ($database) {
                DatabaseLoader::dropTables(DB::getTablesByPrefix());
                DatabaseLoader::load(
                    SqlReader::fromStream($this->backup->getDatabaseDump()),
                    $this->backup->getMetaData('db_prefix'),
                    _dbprefix
                );
            }

            // empty directories
            foreach ($directories as $directory) {
                Filesystem::purgeDirectory(_root . $directory, array('keep_dir' => true));
            }

            // extract directories
            $this->backup->extract($directories, _root);
            
            // clear cache
            Core::$cache->clear();

            // force install check
            Core::updateSetting('install_check', 1);
        }

        return empty($errors);
    }
}
