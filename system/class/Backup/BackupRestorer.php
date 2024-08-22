<?php

namespace Sunlight\Backup;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;
use Sunlight\Logger;
use Sunlight\Plugin\Plugin;
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
     * 
     * @param-out string[] $errors
     */
    function validate(bool $expectedIsPatch, ?array &$errors = null): bool
    {
        $errors = $this->backup->getMetaDataErrors();

        if (!empty($errors)) {
            return false;
        }

        if ($expectedIsPatch !== ($this->backup->getMetaData('patch') !== null)) {
            $errors[] = $expectedIsPatch
                ? 'expected a patch, but this archive is a backup'
                : 'expected a backup, but this archive is a patch';

            return false;
        }

        return true;
    }

    /**
     * Restore the backup
     *
     * @param bool $database restore the database 1/0
     * @param array|null $directories directory paths to restore (from backup's metadata), null = all
     * @param array|null $files file paths to restore (from backup's metadata), null = all
     * @param-out string[] $errors
     */
    function restore(bool $database, ?array $directories = null, ?array $files = null, ?array &$errors = null): bool
    {
        $errors = [];

        $patch = $this->backup->getMetaData('patch');
        $isPatch = $patch !== null;
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
            $directories = array_filter($directories, function (string $directory) {
                if (preg_match('{plugins/(\w+)/(' . Plugin::NAME_PATTERN . ')$}AD', $directory, $match)) {
                    // don't extract a plugin from a patch if it is not installed
                    return is_dir(SL_ROOT . 'plugins/' . $match[1] . '/' . $match[2]);
                }

                return true;
            });

            $filesToRemove = $this->normalizeExistingPaths($patch['files_to_remove']);
            $directoriesToRemove = $this->normalizeExistingPaths($patch['directories_to_remove']);
            $directoriesToPurge = $this->normalizeExistingPaths(array_merge($directories, $patch['directories_to_purge']));
        } else {
            $filesToRemove = [];
            $directoriesToRemove = [];
            $directoriesToPurge = $this->normalizeExistingPaths($directories);
        }

        // verify what we are restoring
        if (!$isPatch && !$database && empty($directories) && empty($files)) {
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
            $failedPaths = [];

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
                    DB::$prefix,
                    $this->backup->getMetaData('db_engine'),
                    DB::$engine
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
            switch (true) {
                // system directory
                case $directory === SL_ROOT . 'system':
                    $this->clearDirectory($directory, [
                        'backup' => true, // backup directory contains the backup
                        'cache' => true, // cache gets cleared later anyway
                    ]);
                    break;

                // admin directory
                case $directory === SL_ROOT . 'admin':
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
            Filesystem::removeDirectory($directory);
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
        Settings::update('install_check', '', false);

        // run patch scripts
        if ($isPatch) {
            foreach ($patch['patch_scripts'] as $patchScriptPath) {
                $patchScript = $this->backup->getFile($patchScriptPath);

                if ($patchScript !== null) {
                    $this->runPhpScript($patchScript);
                }
            }
        }

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
    private function normalizeExistingPaths(array $paths): array
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
        Filesystem::emptyDirectory($directory, function (\SplFileInfo $item) use ($excludedFilenameMap) {
            return !isset($excludedFilenameMap[$item->getFilename()]);
        });
    }

    private function runPhpScript(string $phpScript): void
    {
        if (strncmp('<?php', $phpScript, 5) === 0) {
            $phpScript = substr($phpScript, 5);
        }

        eval($phpScript);
    }
}
