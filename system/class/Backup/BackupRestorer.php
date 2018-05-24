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
     * @param array|null $errors
     * @return bool
     */
    public function validate(array &$errors = null)
    {
        $errors = $this->backup->getMetaDataErrors();

        return empty($errors);
    }

    /**
     * Restore the backup
     *
     * @param bool       $database    restore the database 1/0
     * @param array      $directories directory paths to restore (from backup's metadata), null = all
     * @param array      $files       file paths to restore (from backup's metadata), null = all
     * @param array|null $errors
     * @return bool
     */
    public function restore($database, array $directories = null, array $files = null, array &$errors = null)
    {
        $errors = array();

        $isPatch = $this->backup->getMetaData('is_patch');
        $database = $database && $this->backup->hasDatabaseDump();

        // defaults
        if ($directories === null) {
            $directories = $this->backup->getMetaData('directory_list');
        }
        if ($files === null) {
            $files = $this->backup->getMetaData('file_list');
        }

        // normalize lists
        $files = $this->normalizePathList($files, $this->backup->getMetaData('file_list'));
        $directories = $this->normalizePathList($directories, $this->backup->getMetaData('directory_list'));

        if ($isPatch) {
            $filesToRemove = $this->normalizePathList($this->backup->getMetaData('files_to_remove'), null, true, true);
            $directoriesToRemove = $this->normalizePathList($this->backup->getMetaData('directories_to_remove'), null, true, true);
            $directoriesToPurge = $this->normalizePathList($this->backup->getMetaData('directories_to_purge'), null, true, true);
        } else {
            $filesToRemove = array();
            $directoriesToRemove = array();
            $directoriesToPurge = $this->normalizePathList($directories, null, true, true);
        }

        // verify what we are restoring
        if (!$database && empty($directories) && empty($files)) {
            $errors[] = 'nothing to restore';
        }

        // verify files
        foreach ($files as $file) {
            if (is_file($file) && !is_writable(_root . $file)) {
                $errors[] = sprintf('cannot write to "%s", please check privileges (%s)', $file);
            }
        }
        foreach ($filesToRemove as $file) {
            if (!is_writable($file)) {
                $errors[] = sprintf('cannot write to "%s", please check privileges (%s)', $file);
            }
        }

        // verify directories
        foreach (array_merge($directoriesToRemove, $directoriesToPurge) as $directory) {
            if (!Filesystem::checkDirectory($directory, true, $failedPaths)) {
                $failedPathsString = implode(', ', array_slice($failedPaths, 0, 3));
                if (sizeof($failedPaths) > 3) {
                    $failedPathsString .= sprintf(' and %d more', sizeof($failedPaths) - 3);
                }

                $errors[] = sprintf('cannot write to "%s", please check privileges (%s)', $failedPathsString);
            }
        }

        if ($errors) {
            return false;
        }

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
            Filesystem::purgeDirectory($directory, array('keep_dir' => true));
        }
        foreach ($directoriesToRemove as $directory) {
            Filesystem::purgeDirectory($directory);
        }
        foreach ($filesToRemove as $file) {
            if (is_file($file)) {
                unlink($file);
            }
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

        return true;
    }

    /**
     * @param string[]      $paths
     * @param string[]|null $allowedValues      list of allowed values in $paths
     * @param bool          $addRootPath        prefix normalized paths with _root 1/0
     * @param bool          $excludeNonexistent skip nonexistent paths 1/0
     * @return array
     */
    protected function normalizePathList(array $paths, array $allowedValues = null, $addRootPath = false, $excludeNonexistent = false)
    {
        if ($allowedValues) {
            $paths = array_intersect($allowedValues, $paths);
        }

        $normalizedPaths = array();

        foreach ($paths as $path) {
            if ($excludeNonexistent && !file_exists(_root . $path)) {
                continue;
            }

            $normalizedPath = $path;

            if ($addRootPath) {
                $normalizedPath = _root . $normalizedPath;
            }

            $normalizedPaths[] = $normalizedPath;
        }

        return $normalizedPaths;
    }
}
