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
     * @param array      $files       file paths to restore (from backup's metadata), null = all
     * @param array|null &$errors
     * @return bool
     */
    public function restore($database, array $directories = null, array $files = null, &$errors = null)
    {
        $errors = array();

        $isPatch = $this->backup->getMetaData('is_patch');

        // normalize arguments
        $database = $database && $this->backup->hasDatabaseDump();

        if ($directories === null) {
            $directories = $this->backup->getMetaData('directory_list');
        } else {
            $directories = array_intersect($this->backup->getMetaData('directory_list'), $directories);
        }

        if ($files === null) {
            $files = $this->backup->getMetaData('file_list');
        } else {
            $files = array_intersect($this->backup->getMetaData('file_list'), $files);
        }

        if ($isPatch) {
            $filesToRemove = $this->backup->getMetaData('files_to_remove');
            $directoriesToRemove = $this->backup->getMetaData('directories_to_remove');
            $directoriesToPurge = $this->backup->getMetaData('directories_to_purge');
        } else {
            $filesToRemove = array();
            $directoriesToRemove = array();
            $directoriesToPurge = $directories;
        }

        // verify what we are restoring
        if (!$database && empty($directories) && empty($files)) {
            $errors[] = 'nothing to restore';
        } else {
            // verify files
            foreach (array_merge($files, $filesToRemove) as $file) {
                $fullPath = _root . $file;

                if (is_file($fullPath) && !is_writable($fullPath)) {
                    $errors[] = sprintf('cannot write to "%s", please check privileges (%s)', $fullPath);
                }
            }

            // verify directories
            foreach (array_merge($directoriesToRemove, $directoriesToPurge) as $directory) {
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
                if (!$this->backup->getMetaData('is_patch')) {
                    DatabaseLoader::dropTables(DB::getTablesByPrefix());
                }

                DatabaseLoader::load(
                    SqlReader::fromStream($this->backup->getDatabaseDump()),
                    $this->backup->getMetaData('db_prefix'),
                    _dbprefix
                );
            }

            // filesystem cleanup
            foreach ($directoriesToPurge as $directory) {
                Filesystem::purgeDirectory(_root . $directory, array('keep_dir' => true));
            }
            foreach ($directoriesToRemove as $directory) {
                Filesystem::purgeDirectory(_root . $directory);
            }
            foreach ($filesToRemove as $file) {
                unlink($file);
            }

            // extract directories
            if (!empty($directories)) {
                $this->backup->extractDirectories($directories, _root);
            }

            // extract files
            if (!empty($files)) {
                $this->backup->extractFiles($files, _root);
            }
            
            // clear cache
            Core::$cache->clear();

            // force install check
            Core::updateSetting('install_check', 1);
        }

        return empty($errors);
    }
}
