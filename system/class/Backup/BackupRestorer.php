<?php

namespace Sunlight\Backup;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;
use Sunlight\Logger;
use Sunlight\Settings;
use Sunlight\Util\ClassPreloader;
use Sunlight\Util\Filesystem;

class BackupRestorer
{
    /** @var Backup */
    private $backup;

    function __construct(Backup $backup)
    {
        $this->backup = $backup;
    }

    /**
     * Validate the backup
     */
    function validate(?array &$errors = null): bool
    {
        $errors = $this->backup->getMetaDataErrors();

        return empty($errors);
    }

    /**
     * Restore the backup
     *
     * @param bool $database restore the database 1/0
     * @param array|null $directories directory paths to restore (from backup's metadata), null = all
     * @param array|null $files file paths to restore (from backup's metadata), null = all
     */
    function restore(bool $database, ?array $directories = null, ?array $files = null, ?array &$errors = null): bool
    {
        $errors = [];

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
        $files = array_intersect($this->backup->getMetaData('file_list'), $files);
        $directories = array_intersect($this->backup->getMetaData('directory_list'), $directories);

        if ($isPatch) {
            $filesToRemove = $this->normalizePathList($this->backup->getMetaData('files_to_remove'));
            $directoriesToRemove = $this->normalizePathList($this->backup->getMetaData('directories_to_remove'));
            $directoriesToPurge = $this->normalizePathList($this->backup->getMetaData('directories_to_purge'));
        } else {
            $filesToRemove = [];
            $directoriesToRemove = [];
            $directoriesToPurge = $this->normalizePathList($directories);
        }

        // verify what we are restoring
        if (!$database && empty($directories) && empty($files)) {
            $errors[] = 'nothing to restore';
        }

        // verify files
        foreach ($files as $file) {
            if (is_file($file) && !is_writable(SL_ROOT . $file)) {
                $errors[] = sprintf('cannot write to "%s", please check privileges', $file);
            }
        }

        foreach ($filesToRemove as $file) {
            if (!is_writable($file)) {
                $errors[] = sprintf('cannot write to "%s", please check privileges', $file);
            }
        }

        // verify directories
        foreach (array_merge($directoriesToRemove, $directoriesToPurge) as $directory) {
            if (!Filesystem::checkDirectory($directory, true, $failedPaths)) {
                $failedPathsString = implode(', ', array_slice($failedPaths, 0, 3));

                if (count($failedPaths) > 3) {
                    $failedPathsString .= sprintf(' and %d more', count($failedPaths) - 3);
                }

                $errors[] = sprintf('cannot write to "%s", please check privileges', $failedPathsString);
            }
        }

        if ($errors) {
            return false;
        }

        // prepare for restoration
        Settings::overwrite('cron_auto', '0'); // prevent automatic cron task execution
        Core::$eventEmitter->clearListeners(); // unregister all extend listeners
        $this->preloadAllSystemClasses(); // preload all system classes

        // load database
        if ($database) {
            DB::transactional(function () use ($isPatch) {
                if (!$isPatch) {
                    DatabaseLoader::dropTables(DB::getTablesByPrefix());
                }

                DatabaseLoader::load(
                    new SqlReader($this->backup->getDatabaseDump()),
                    $this->backup->getMetaData('db_prefix'),
                    DB::$prefix
                );
            });

            Logger::notice(
                'system',
                sprintf('Loaded database dump from a %s', $isPatch ? 'patch' : 'backup'),
                ['path' => $this->backup->getPath(), 'metadata' => $this->backup->getMetaData()]
            );
        }

        // filesystem cleanup
        foreach ($directoriesToPurge as $directory) {
            switch ($directory) {
                // system directory
                case SL_ROOT . 'system':
                    $this->clearDirectory($directory, [
                        'backup' => true, // backup directory contains the backup
                        'cache' => true, // cache gets cleared later anyway
                    ]);
                    break;

                // admin directory
                case SL_ROOT . 'admin':
                    $this->clearDirectory($directory, [
                        'index.php' => true, // https://github.com/php/php-src/issues/7910
                    ]);
                    break;

                // other directories
                default:
                    $this->clearDirectory($directory);
                    break;
            }
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
            $this->backup->extractDirectories($directories, SL_ROOT);
        }

        // extract files
        if (!empty($files)) {
            $this->backup->extractFiles($files, SL_ROOT);
        }

        // log
        Logger::notice(
            'system',
            sprintf('Finished %s', $isPatch ? 'applying a patch' : 'restoring a backup'),
            ['path' => $this->backup->getPath(), 'metadata' => $this->backup->getMetaData(), 'directories' => $directories, 'files' => $files]
        );

        // clear cache
        Core::$cache->clear();

        // force install check
        Settings::update('install_check', '');

        return true;
    }

    /**
     * Get a pessimistic restoration time estimate
     */
    function estimateFullRestorationTime(): int
    {
        $requiredTime = 2;

        if ($this->backup->hasDatabaseDump()) {
            // approx 500kB/s
            $requiredTime += $this->backup->getDatabaseDumpSize() / 500000;
        }

        // approx 2M/s
        $requiredTime += $this->backup->getTotalDataSize() / 2000000;

        return (int) ceil($requiredTime);
    }

    /**
     * @param string[] $paths
     */
    private function normalizePathList(array $paths): array
    {
        $normalizedPaths = [];

        foreach ($paths as $path) {
            $normalizedPath = realpath(SL_ROOT . $path);

            if ($normalizedPath !== false) {
                $normalizedPaths[] = $normalizedPath;
            }
        }

        return $normalizedPaths;
    }

    private function preloadAllSystemClasses(): void
    {
        $preloader = new ClassPreloader();
        $preloader->addPsr4Prefix('Sunlight\\');
        $preloader->addPsr4Prefix('Kuria\\*');
        $preloader->addExcludedClassPattern('Kuria\\Cache\\Psr\\*');
        $preloader->preload();
    }

    private function clearDirectory(string $directory, array $excludedFilenameMap = []): void
    {
        foreach (Filesystem::createIterator($directory) as $item) {
            if (isset($excludedFilenameMap[$item->getFilename()])) {
                continue;
            }

            if ($item->isDir()) {
                Filesystem::purgeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
    }
}
