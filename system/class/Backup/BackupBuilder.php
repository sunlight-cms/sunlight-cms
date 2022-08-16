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
        'system/backup/.gitkeep' => ['static' => true, 'dynamic' => false],
        'system/cache/.gitkeep' => ['static' => true, 'dynamic' => false],
        'system/tmp/.gitkeep' => ['static' => true, 'dynamic' => false],
    ];

    /** @var array[] pattern list */
    private $excludedPathMap = [
        'system/backup/*' => ['static' => true, 'dynamic' => true],
        'system/cache/*' => ['static' => true, 'dynamic' => true],
        'system/tmp/*' => ['static' => true, 'dynamic' => true],
    ];

    /** @var bool */
    private $fullBackup = true;

    /* @var bool */
    private $databaseDumpEnabled = true;

    /** @var bool */
    private $prefillConfigFile = true;

    function isFullBackup(): bool
    {
        return $this->fullBackup;
    }

    function setFullBackup(bool $fullBackup): void
    {
        $this->fullBackup = $fullBackup;
    }

    function isDatabaseDumpEnabled(): bool
    {
        return $this->databaseDumpEnabled;
    }

    function setDatabaseDumpEnabled(bool $databaseDumpEnabled): void
    {
        $this->databaseDumpEnabled = $databaseDumpEnabled;
    }

    function getPrefillConfigFile(): bool
    {
        return $this->prefillConfigFile;
    }

    function setPrefillConfigFile(bool $prefillConfigFile): void
    {
        $this->prefillConfigFile = $prefillConfigFile;
    }

    function getStaticPaths(): array
    {
        return $this->staticPathList;
    }

    /**
     * @param string $path path to a file or a directory, relative to system root
     */
    function addStaticPath(string $path): void
    {
        $this->staticPathList[] = $path;
    }

    function getEmptyDirectories(): array
    {
        return $this->emptyDirPathList;
    }

    /**
     * @param string $path path to a directory, relative to system root
     */
    function addEmptyDirectory(string $path): void
    {
        $this->emptyDirPathList[] = $path;
    }
    
    function hasDynamicPath(string $name): bool
    {
        return isset($this->dynamicPathMap[$name]);
    }

    function getDynamicPath(string $name): array
    {
        $this->ensureDynamicPathNameIsValid($name);

        return $this->dynamicPathMap[$name];
    }

    function getDynamicPathNames(): array
    {
        return array_keys($this->dynamicPathMap);
    }

    /**
     * Add/extend a dynamic path
     *
     * @param string $name dynamic path name (consisting of [a-zA-Z0-9_] only)
     * @param array $paths array of relative directory/file paths
     * @throws \InvalidArgumentException if the name is empty or contains illegal characters
     */
    function addDynamicPath(string $name, array $paths): void
    {
        if (!preg_match('{[a-zA-Z0-9_]+$}AD', $name)) {
            throw new \InvalidArgumentException('The name is empty or contains illegal characters');
        }

        $this->dynamicPathMap[$name] = isset($this->dynamicPathMap[$name])
            ? array_merge($this->dynamicPathMap[$name], $paths)
            : $paths;
    }

    function removeDynamicPath(string $name): void
    {
        unset($this->dynamicPathMap[$name], $this->disabledDynamicPathMap[$name]);
    }

    function isDynamicPathEnabled(string $name): bool
    {
        $this->ensureDynamicPathNameIsValid($name);

        return !isset($this->disabledDynamicPathMap[$name]);
    }

    function disableDynamicPath(string $name): void
    {
        $this->ensureDynamicPathNameIsValid($name);

        $this->disabledDynamicPathMap[$name] = true;
    }

    function enableDynamicPath(string $name): void
    {
        $this->ensureDynamicPathNameIsValid($name);

        unset($this->disabledDynamicPathMap[$name]);
    }

    function isDynamicPathOptional(string $name): bool
    {
        $this->ensureDynamicPathNameIsValid($name);

        return isset($this->optionalDynamicPathMap[$name]);
    }

    function makeDynamicPathOptional(string $name): void
    {
        $this->ensureDynamicPathNameIsValid($name);

        $this->optionalDynamicPathMap[$name] = true;
    }

    function makeDynamicPathRequired(string $name): void
    {
        $this->ensureDynamicPathNameIsValid($name);

        unset($this->optionalDynamicPathMap[$name]);
    }

    /**
     * Make a path included
     *
     * @param bool $static affect static paths 1/0
     * @param bool $dynamic affect dynamic paths 1/0
     */
    function includePath(string $pattern, bool $static = true, bool $dynamic = true): void
    {
        $this->includedPathMap[$pattern] = ['static' => $static, 'dynamic' => $dynamic];
    }

    /**
     * Make a path excluded
     *
     * @param bool $static affect static paths 1/0
     * @param bool $dynamic affect dynamic paths 1/0
     */
    function excludePath(string $pattern, bool $static = true, bool $dynamic = true): void
    {
        $this->excludedPathMap[$pattern] = ['static' => $static, 'dynamic' => $dynamic];
    }

    /**
     * Build the backup
     */
    function build(): TemporaryFile
    {
        $tmpFile = Filesystem::createTmpFile();
        $backup = new Backup($tmpFile->getPathname());
        $backup->create();
        $this->write($backup);
        $backup->close();

        return $tmpFile;
    }

    protected function write(Backup $backup): void
    {
        if ($this->databaseDumpEnabled) {
            $backup->addDatabaseDump($this->dumpDatabase(), DB::$prefix);
        }

        if ($this->fullBackup) {
            foreach ($this->staticPathList as $path) {
                $backup->addPath($path, function ($dataPath) {
                    return $this->filterPath($dataPath, true);
                });
            }

            foreach ($this->emptyDirPathList as $path) {
                $backup->addEmptyDirectory($path);
            }

            foreach ($this->dynamicPathMap as $name => $paths) {
                $enabled = !$this->isDynamicPathOptional($name) || $this->isDynamicPathEnabled($name);

                foreach ($paths as $path) {
                    if ($enabled) {
                        $backup->addPath($path, function ($dataPath) {
                            return $this->filterPath($dataPath, false, true);
                        });
                    } elseif (is_dir(SL_ROOT . $path)) {
                        $backup->addEmptyDirectory($path);
                    }
                }
            }

            $backup->addFileFromString('config.php', $this->generateConfigFile(), false);
        } else {
            foreach ($this->dynamicPathMap as $name => $paths) {
                if ($this->isDynamicPathEnabled($name)) {
                    foreach ($paths as $path) {
                        $backup->addPath($path);
                    }
                }
            }
        }
    }

    protected function filterPath(string $dataPath, bool $isStatic = false, bool $isDynamic = false): bool
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
     * @throws \OutOfBoundsException if no such dynamic path exists
     */
    private function ensureDynamicPathNameIsValid(string $name): void
    {
        if (!isset($this->dynamicPathMap[$name])) {
            throw new \OutOfBoundsException(sprintf('Unknown dynamic path "%s"', $name));
        }
    }

    private function dumpDatabase(): TemporaryFile
    {
        $tables = DB::getTablesByPrefix();

        $dumper = new SqlDumper();
        $dumper->addTables($tables);

        return $dumper->dump();
    }

    private function generateConfigFile(): string
    {
        $phpFileBuilder = PhpTemplate::fromFile(SL_ROOT . 'system/config_template.php');

        if ($this->prefillConfigFile) {
            $vars = [
                'db.prefix' => substr(DB::$prefix, 0, -1),
                'app_id' => Core::$appId,
                'fallback_lang' => Core::$fallbackLang,
            ];
        } else {
            $vars = [];
        }

        return $phpFileBuilder->compile($vars);
    }
}
