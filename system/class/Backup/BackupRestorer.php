<?php

namespace Sunlight\Backup;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;
use Sunlight\Logger;
use Sunlight\Settings;
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
        $files = $this->normalizePathList($files, $this->backup->getMetaData('file_list'));
        $directories = $this->normalizePathList($directories, $this->backup->getMetaData('directory_list'));

        if ($isPatch) {
            $filesToRemove = $this->normalizePathList($this->backup->getMetaData('files_to_remove'), null, true, true);
            $directoriesToRemove = $this->normalizePathList($this->backup->getMetaData('directories_to_remove'), null, true, true);
            $directoriesToPurge = $this->normalizePathList($this->backup->getMetaData('directories_to_purge'), null, true, true);
        } else {
            $filesToRemove = [];
            $directoriesToRemove = [];
            $directoriesToPurge = $this->normalizePathList($directories, null, true, true);
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

        // preload all system classes before any directories are restored
        if (!empty($directories)) {
            $this->preloadAllSystemClasses();
        }

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
        $systemRealPath = realpath(SL_ROOT . 'system');

        foreach ($directoriesToPurge as $directory) {
            if (realpath($directory) === $systemRealPath) {
                // the "system" directory needs special handling because backups are stored in it
                $this->purgeSystemDirectory();
            } else {
                // other dirs
                Filesystem::purgeDirectory($directory, ['keep_dir' => true]);
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
     * @param string[]|null $allowedValues list of allowed values in $paths
     * @param bool $addRootPath prefix normalized paths with SL_ROOT 1/0
     * @param bool $excludeNonexistent skip nonexistent paths 1/0
     */
    private function normalizePathList(array $paths, ?array $allowedValues = null, bool $addRootPath = false, bool $excludeNonexistent = false): array
    {
        if ($allowedValues) {
            $paths = array_intersect($allowedValues, $paths);
        }

        $normalizedPaths = [];

        foreach ($paths as $path) {
            if ($excludeNonexistent && !file_exists(SL_ROOT . $path)) {
                continue;
            }

            $normalizedPath = $path;

            if ($addRootPath) {
                $normalizedPath = SL_ROOT . $normalizedPath;
            }

            $normalizedPaths[] = $normalizedPath;
        }

        return $normalizedPaths;
    }

    private function preloadAllSystemClasses(): void
    {
        $classMap = require SL_ROOT . 'vendor/composer/autoload_classmap.php';

        foreach ($classMap as $class => $path) {
            if ($this->shouldPreloadClass($class)) {
                include_once $path;
            }
        }
    }

    private function shouldPreloadClass(string $class): bool
    {
        $nsSepPos = strpos($class, '\\');

        if ($nsSepPos === false) {
            return false; // no namespace
        }

        $rootNs = substr($class, 0, $nsSepPos);

        if ($rootNs !== 'Kuria' && $rootNs !== 'Sunlight') {
            return false; // only load system classes and kuria libs
        }

        $lastNsSepPos = strrpos($class, '\\', $nsSepPos + 1);

        if ($lastNsSepPos !== false && substr($class, 0, $lastNsSepPos) === 'Kuria\Cache\Psr') {
            return false; // don't load psr cache classes (psr interfaces are not available)
        }

        return true;
    }

    private function purgeSystemDirectory(): void
    {
        $preservedDirMap = [
            'backup' => true, // has the backups in it, including the current one
            'cache' => true, // will be cleared at the end of restore()
        ];

        foreach (Filesystem::createIterator(SL_ROOT . 'system') as $item) {
            if ($item->isDir()) {
                if (!isset($preservedDirMap[$item->getFilename()])) {
                    Filesystem::purgeDirectory($item->getPathname());
                }
            } else {
                unlink($item->getPathname());
            }
        }
    }
}
