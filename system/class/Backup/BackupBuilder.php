<?php

namespace Sunlight\Backup;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\SqlDumper;
use Sunlight\Util\Filesystem;
use Sunlight\Util\PhpTemplate;
use Sunlight\Util\TemporaryFile;

/**
 * Backup archive builder
 */
class BackupBuilder
{
    /** Backup type - partial */
    const TYPE_PARTIAL = 0;
    /** Backup type - full */
    const TYPE_FULL = 1;

    /** @var string[] */
    private $staticPathList = [
        'admin',
        'system',
        'vendor',
        'index.php',
        'composer.json',
        'robots.txt',
        'favicon.ico',
    ];

    /** @var string[] */
    private $emptyDirPathList = [
        'images/thumb',
    ];

    /** @var array[] name => paths */
    private $dynamicPathMap = [
        'plugins' => ['plugins'],
        'upload' => ['upload'],
        'images_user' => [
            'images/avatars',
            'images/groupicons',
        ],
        'images_articles' => [
            'images/articles',
        ],
        'images_galleries' => [
            'images/galleries',
        ],
    ];

    /** @var bool[] name => true */
    private $disabledDynamicPathMap = [];

    /** @var bool[] name => true */
    private $optionalDynamicPathMap = [
        'upload' => true,
        'images_user' => true,
        'images_articles' => true,
        'images_galleries' => true,
    ];

    /** @var array[] pattern list */
    private $includedPathMap = [
        'system/backup/.htaccess' => ['static' => true, 'dynamic' => false],
        'system/backup/.gitkeep' => ['static' => true, 'dynamic' => false],
        'system/cache/.htaccess' => ['static' => true, 'dynamic' => false],
        'system/cache/.gitkeep' => ['static' => true, 'dynamic' => false],
        'system/tmp/.htaccess' => ['static' => true, 'dynamic' => false],
        'system/tmp/.gitkeep' => ['static' => true, 'dynamic' => false],
    ];

    /** @var array[] pattern list */
    private $excludedPathMap = [
        'system/backup/*' => ['static' => true, 'dynamic' => true],
        'system/cache/*' => ['static' => true, 'dynamic' => true],
        'system/tmp/*' => ['static' => true, 'dynamic' => true],
    ];

    /* @var bool */
    private $databaseDumpEnabled = true;

    /**
     * See if database dump is enabled
     *
     * @return bool
     */
    function isDatabaseDumpEnabled(): bool
    {
        return $this->databaseDumpEnabled;
    }

    /**
     * Toggle database dump
     *
     * @param bool $databaseDumpEnabled
     * @return $this
     */
    function setDatabaseDumpEnabled(bool $databaseDumpEnabled): self
    {
        $this->databaseDumpEnabled = $databaseDumpEnabled;

        return $this;
    }

    /**
     * Get list of static paths
     *
     * @return array
     */
    function getStaticPaths(): array
    {
        return $this->staticPathList;
    }

    /**
     * Add a static path
     *
     * @param string $path path to a file or a directory, relative to system root
     * @return $this
     */
    function addStaticPath(string $path): self
    {
        $this->staticPathList[] = $path;

        return $this;
    }

    /**
     * Get list of empty directories
     *
     * @return string[]
     */
    function getEmptyDirectories(): array
    {
        return $this->emptyDirPathList;
    }

    /**
     * Add empty directory
     *
     * @param string $path path to a directory, relative to system root
     * @return $this
     */
    function addEmptyDirectory(string $path): self
    {
        $this->emptyDirPathList[] = $path;

        return $this;
    }
    
    /**
     * See if a dynamic path exists
     *
     * @param string $name dynamic path name
     * @return bool
     */
    function hasDynamicPath(string $name): bool
    {
        return isset($this->dynamicPathMap[$name]);
    }

    /**
     * Get paths for a the given dynamic path name
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return array
     */
    function getDynamicPath(string $name): array
    {
        $this->ensureDynamicPathNameIsValid($name);

        return $this->dynamicPathMap[$name];
    }

    /**
     * Get names of all known dynamic paths
     *
     * @return array
     */
    function getDynamicPathNames(): array
    {
        return array_keys($this->dynamicPathMap);
    }

    /**
     * Add/extend a dynamic path
     *
     * @param string $name  dynamic path name (consisting of [a-zA-Z0-9_] only)
     * @param array  $paths array of relative directory/file paths
     * @throws \InvalidArgumentException if the name is empty or contains illegal characters
     * @return $this
     */
    function addDynamicPath(string $name, array $paths): self
    {
        if (!preg_match('{[a-zA-Z0-9_]+$}AD', $name)) {
            throw new \InvalidArgumentException('The name is empty or contains illegal characters');
        }

        $this->dynamicPathMap[$name] = isset($this->dynamicPathMap[$name])
            ? array_merge($this->dynamicPathMap[$name], $paths)
            : $paths;

        return $this;
    }

    /**
     * Remove a dynamic path
     *
     * @param string $name dynamic path name
     * @return $this
     */
    function removeDynamicPath(string $name): self
    {
        unset($this->dynamicPathMap[$name]);
        unset($this->disabledDynamicPathMap[$name]);

        return $this;
    }

    /**
     * See if a dynamic path is enabled
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return bool
     */
    function isDynamicPathEnabled(string $name): bool
    {
        $this->ensureDynamicPathNameIsValid($name);

        return !isset($this->disabledDynamicPathMap[$name]);
    }

    /**
     * Disable a dynamic path
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return $this
     */
    function disableDynamicPath(string $name): self
    {
        $this->ensureDynamicPathNameIsValid($name);

        $this->disabledDynamicPathMap[$name] = true;

        return $this;
    }

    /**
     * Enable a previously disabled dynamic path
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return $this
     */
    function enableDynamicPath(string $name): self
    {
        $this->ensureDynamicPathNameIsValid($name);

        unset($this->disabledDynamicPathMap[$name]);

        return $this;
    }

    /**
     * See if a dynamic path can be disabled in full mode
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return bool
     */
    function isDynamicPathOptional(string $name): bool
    {
        $this->ensureDynamicPathNameIsValid($name);

        return isset($this->optionalDynamicPathMap[$name]);
    }

    /**
     * Make dynamic path optional in full mode
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return $this
     */
    function makeDynamicPathOptional(string $name): self
    {
        $this->ensureDynamicPathNameIsValid($name);

        $this->optionalDynamicPathMap[$name] = true;

        return $this;
    }

    /**
     * Make dynamic path required in full mode
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return $this
     */
    function makeDynamicPathRequired(string $name): self
    {
        $this->ensureDynamicPathNameIsValid($name);

        unset($this->optionalDynamicPathMap[$name]);

        return $this;
    }

    /**
     * Make a path included
     *
     * @param string $pattern
     * @param bool   $static  affect static paths 1/0
     * @param bool   $dynamic affect dynamic paths 1/0
     * @return $this
     */
    function includePath(string $pattern, bool $static = true, bool $dynamic = true): self
    {
        $this->includedPathMap[$pattern] = ['static' => $static, 'dynamic' => $dynamic];

        return $this;
    }

    /**
     * Make a path excluded
     *
     * @param string $pattern
     * @param bool   $static  affect static paths 1/0
     * @param bool   $dynamic affect dynamic paths 1/0
     * @return $this
     */
    function excludePath(string $pattern, bool $static = true, bool $dynamic = true): self
    {
        $this->excludedPathMap[$pattern] = ['static' => $static, 'dynamic' => $dynamic];

        return $this;
    }

    /**
     * Build a backup file
     *
     * @param int $type see BackupBuilder::TYPE_* constants
     * @throws \InvalidArgumentException on invalid type
     * @return TemporaryFile
     */
    function build(int $type): TemporaryFile
    {
        if (self::TYPE_PARTIAL !== $type && self::TYPE_FULL !== $type) {
            throw new \InvalidArgumentException('Invalid type');
        }

        $tmpFile = Filesystem::createTmpFile();
        $backup =  $this->createBackup($tmpFile->getPathname());

        try {
            $backup->create();

            switch ($type) {
                case self::TYPE_PARTIAL:
                    $this->writePartial($backup);
                    break;

                case self::TYPE_FULL:
                    $this->writeFull($backup);
                    break;
            }

            $backup->close();
        } catch (\Exception $e) {
            $backup->discard();
            $tmpFile->discard();

            throw $e;
        }

        return $tmpFile;
    }

    /**
     * Write a partial ZIP backup
     *
     * @param Backup $backup
     */
    private function writePartial(Backup $backup): void
    {
        if ($this->databaseDumpEnabled) {
            $backup->addDatabaseDump($this->dumpDatabase(), _dbprefix);
        }

        foreach ($this->dynamicPathMap as $name => $paths) {
            if ($this->isDynamicPathEnabled($name)) {
                foreach ($paths as $path) {
                    $backup->addPath($path);
                }
            }
        }
    }

    /**
     * Write a full ZIP backup
     *
     * @param Backup $backup
     */
    private function writeFull(Backup $backup): void
    {
        $that = $this;

        if ($this->databaseDumpEnabled) {
            $backup->addDatabaseDump($this->dumpDatabase(), _dbprefix);
        }

        foreach ($this->staticPathList as $path) {
            $backup->addPath($path, function ($dataPath) use ($that) {
                return $that->filterPath($dataPath, true, false);
            });
        }

        foreach ($this->emptyDirPathList as $path) {
            $backup->addEmptyDirectory($path);
        }

        foreach ($this->dynamicPathMap as $name => $paths) {
            $enabled = !$this->isDynamicPathOptional($name) || $this->isDynamicPathEnabled($name);

            foreach ($paths as $path) {
                if ($enabled) {
                    $backup->addPath($path, function ($dataPath) use ($that) {
                        return $that->filterPath($dataPath, false, true);
                    });
                } elseif (is_dir(_root . $path)) {
                    $backup->addEmptyDirectory($path);
                }
            }
        }

        $backup->addFileFromString('config.php', self::generateConfigFile(), false);
    }

    /**
     * Dump the database
     *
     * @return TemporaryFile
     */
    private function dumpDatabase(): TemporaryFile
    {
        $tables = DB::getTablesByPrefix();

        $dumper = new SqlDumper();
        $dumper->addTables($tables);

        return $dumper->dump();
    }

    /**
     * @param string $name
     * @throws \OutOfBoundsException if no such dynamic path exists
     */
    private function ensureDynamicPathNameIsValid(string $name): void
    {
        if (!isset($this->dynamicPathMap[$name])) {
            throw new \OutOfBoundsException(sprintf('Unknown dynamic path "%s"', $name));
        }
    }

    /**
     * Filter a path
     *
     * @param string $dataPath
     * @param bool   $isStatic
     * @param bool   $isDynamic
     * @return bool
     */
    function filterPath(string $dataPath, bool $isStatic = false, bool $isDynamic = false): bool
    {
        foreach ($this->includedPathMap as $pattern => $options) {
            if (
                (
                    $isStatic && $options['static']
                    || $isDynamic && $options['dynamic']
                    || !$isDynamic && !$isStatic
                )
                && fnmatch($pattern, $dataPath, FNM_CASEFOLD)
            ) {
                // included path matched - allow
                return true;
            }
        }

        foreach ($this->excludedPathMap as $pattern => $options) {
            if (
                (
                    $isStatic && $options['static']
                    || $isDynamic && $options['dynamic']
                    || !$isDynamic && !$isStatic
                )
                && fnmatch($pattern, $dataPath, FNM_CASEFOLD)
            ) {
                // exluded path matched - skip
                return false;
            }
        }

        // no match - allow
        return true;
    }

    /**
     * @param string $path
     * @return Backup
     */
    private function createBackup(string $path): Backup
    {
        return new Backup($path);
    }

    /**
     * Generate config.php
     *
     * @return string
     */
    static function generateConfigFile(): string
    {
        $phpFileBuilder = PhpTemplate::fromFile(_root . 'system/config_template.php');

        return $phpFileBuilder->compile([
            'db.prefix' => substr(_dbprefix, 0, -1),
            'app_id' => Core::$appId,
            'fallback_lang' => Core::$fallbackLang,
        ]);
    }
}
