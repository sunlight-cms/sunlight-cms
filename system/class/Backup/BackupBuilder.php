<?php

namespace Sunlight\Backup;

use Kuria\Cache\Util\TemporaryFile;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\SqlDumper;
use Sunlight\Util\PhpTemplate;

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
    protected $staticPathList = array(
        'admin',
        'system',
        'index.php',
        'composer.json',
        'robots.txt',
        'favicon.ico',
    );
    /** @var string[] */
    protected $emptyDirPathList = array(
        'system/backup',
        'system/cache',
        'images/thumb',
        'system/tmp',
    );
    /** @var string[] */
    protected $generatedPathMap = array(
        'config.php' => array(__CLASS__, 'generateConfigFile'),
    );
    /** @var array[] name => paths */
    protected $dynamicPathMap = array(
        'plugins' => array('plugins'),
        'upload' => array('upload'),
        'images_user' => array(
            'images/avatars',
            'images/groupicons',
        ),
        'images_articles' => array(
            'images/articles',
        ),
        'images_galleries' => array(
            'images/galleries',
        ),
    );
    /** @var bool[] name => true */
    protected $disabledDynamicPathMap = array();
    /** @var bool[] name => true */
    protected $optionalDynamicPathMap = array(
        'upload' => true,
        'images_user' => true,
        'images_articles' => true,
        'images_galleries' => true,
    );
    /** @var array[] regex list */
    protected $includedPathMap = array(
        '~^images/[^/]+$~' => array('static' => true, 'dynamic' => true),
    );
    /** @var array[] regex list */
    protected $excludedPathMap = array(
        '~^system/backup/~' => array('static' => true, 'dynamic' => true),
        '~^system/cache/~' => array('static' => true, 'dynamic' => true),
        '~^images/~' => array('static' => true, 'dynamic' => false),
        '~^system/tmp/~' => array('static' => true, 'dynamic' => true),
    );
    /* @var bool */
    protected $databaseDumpEnabled = true;

    /**
     * See if database dump is enabled
     *
     * @return bool
     */
    public function isDatabaseDumpEnabled()
    {
        return $this->databaseDumpEnabled;
    }

    /**
     * Toggle database dump
     *
     * @param bool $databaseDumpEnabled
     * @return static
     */
    public function setDatabaseDumpEnabled($databaseDumpEnabled)
    {
        $this->databaseDumpEnabled = $databaseDumpEnabled;

        return $this;
    }

    /**
     * Get list of static paths
     *
     * @return array
     */
    public function getStaticPaths()
    {
        return $this->staticPathList;
    }

    /**
     * Add a static path
     *
     * @param string $path path to a file or a directory, relative to system root
     * @return static
     */
    public function addStaticPath($path)
    {
        $this->staticPathList[] = $path;

        return $this;
    }

    /**
     * Get list of empty directories
     *
     * @return array
     */
    public function getEmptyDirectories()
    {
        return $this->emptyDirPathList;
    }

    /**
     * Add empty directory
     *
     * @param string $path path to a directory, relative to system root
     * @return static
     */
    public function addEmptyDirectory($path)
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
    public function hasDynamicPath($name)
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
    public function getDynamicPath($name)
    {
        $this->ensureDynamicPathNameIsValid($name);

        return $this->dynamicPathMap[$name];
    }

    /**
     * Get names of all known dynamic paths
     *
     * @return array
     */
    public function getDynamicPathNames()
    {
        return array_keys($this->dynamicPathMap);
    }

    /**
     * Add/extend a dynamic path
     *
     * @param string $name  dynamic path name (consisting of [a-zA-Z0-9_] only)
     * @param array  $paths array of relative directory/file paths
     * @throws \InvalidArgumentException if the name is empty or contains illegal characters
     * @return static
     */
    public function addDynamicPath($name, array $paths)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
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
     * @return static
     */
    public function removeDynamicPath($name)
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
    public function isDynamicPathEnabled($name)
    {
        $this->ensureDynamicPathNameIsValid($name);

        return !isset($this->disabledDynamicPathMap[$name]);
    }

    /**
     * Disable a dynamic path
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return static
     */
    public function disableDynamicPath($name)
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
     * @return static
     */
    public function enableDynamicPath($name)
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
    public function isDynamicPathOptional($name)
    {
        $this->ensureDynamicPathNameIsValid($name);

        return isset($this->optionalDynamicPathMap[$name]);
    }

    /**
     * Make dynamic path optional in full mode
     *
     * @param string $name dynamic path name
     * @throws \OutOfBoundsException if no such dynamic path exists
     * @return static
     */
    public function makeDynamicPathOptional($name)
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
     * @return static
     */
    public function makeDynamicPathRequired($name)
    {
        $this->ensureDynamicPathNameIsValid($name);

        unset($this->optionalDynamicPathMap[$name]);

        return $this;
    }

    /**
     * Add a generated path
     *
     * @param string   $path     relative archive path
     * @param callable $callback callback(): string
     * @return static
     */
    public function addGeneratedPath($path, $callback)
    {
        $this->generatedPathMap[$path] = $callback;

        return $this;
    }

    /**
     * Make a path included
     *
     * @param string $regexp
     * @param bool   $static  affect static paths 1/0
     * @param bool   $dynamic affect dynamic paths 1/0
     * @return static
     */
    public function includePath($regexp, $static = true, $dynamic = true)
    {
        $this->includedPathMap[$regexp] = array('static' => $static, 'dynamic' => $dynamic);

        return $this;
    }

    /**
     * Make a path excluded
     *
     * @param string $regexp
     * @param bool   $static  affect static paths 1/0
     * @param bool   $dynamic affect dynamic paths 1/0
     * @return static
     */
    public function excludePath($regexp, $static = true, $dynamic = true)
    {
        $this->excludedPathMap[$regexp] = array('static' => $static, 'dynamic' => $dynamic);

        return $this;
    }

    /**
     * Build a backup file
     *
     * @param int $type see BackupBuilder::TYPE_*
     * @throws \InvalidArgumentException on invalid type
     * @return TemporaryFile
     */
    public function build($type)
    {
        if (static::TYPE_PARTIAL !== $type && static::TYPE_FULL !== $type) {
            throw new \InvalidArgumentException('Invalid type');
        }

        $tmpFile = _tmpFile();

        try {
            switch ($type) {
                case static::TYPE_PARTIAL:
                    $this->writePartial($tmpFile);
                    break;

                case static::TYPE_FULL:
                    $this->writeFull($tmpFile);
                    break;
            }
        } catch (\Exception $e) {
            $tmpFile->discard();

            throw $e;
        }

        return $tmpFile;
    }

    /**
     * Write a partial ZIP backup
     *
     * @param TemporaryFile $tmpFile
     */
    protected function writePartial(TemporaryFile $tmpFile)
    {
        $backup = new Backup($tmpFile->getPathname());
        $backup->create();

        try {
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

            $backup->close();
        } catch (\Exception $e) {
            $backup->discard();

            throw $e;
        }
    }

    /**
     * Write a full ZIP backup
     *
     * @param TemporaryFile $tmpFile
     */
    protected function writeFull(TemporaryFile $tmpFile)
    {
        $backup = new Backup($tmpFile->getPathname());
        $backup->create();

        try {
            $that = $this;

            $backup->addDatabaseDump($this->dumpDatabase(), _dbprefix);

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
            foreach ($this->generatedPathMap as $path => $callback) {
                $backup->addFileFromString($path, call_user_func($callback));
            }

            $backup->close();
        } catch (\Exception $e) {
            $backup->discard();

            throw $e;
        }
    }

    /**
     * Dump the database
     *
     * @return TemporaryFile
     */
    protected function dumpDatabase()
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
    protected function ensureDynamicPathNameIsValid($name)
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
    public function filterPath($dataPath, $isStatic = false, $isDynamic = false)
    {
        foreach ($this->includedPathMap as $regex => $options) {
            if (
                (
                    $isStatic && $options['static']
                    || $isDynamic && $options['dynamic']
                    || !$isDynamic && !$isStatic
                )
                && preg_match($regex, $dataPath)
            ) {
                // included path matched - allow
                return true;
            }
        }

        foreach ($this->excludedPathMap as $regex => $options) {
            if (
                (
                    $isStatic && $options['static']
                    || $isDynamic && $options['dynamic']
                    || !$isDynamic && !$isStatic
                )
                && preg_match($regex, $dataPath)
            ) {
                // exluded path matched - skip
                return false;
            }
        }

        // no match - allow
        return true;
    }

    /**
     * Generate config.php
     *
     * @return string
     */
    public static function generateConfigFile()
    {
        $phpFileBuilder = PhpTemplate::fromFile(_root . 'system/config_template.php');

        return $phpFileBuilder->compile(array(
            'db.prefix' => substr(_dbprefix, 0, -1),
            'url' => Core::$url,
            'app_id' => Core::$appId,
            'fallback_lang' => Core::$fallbackLang,
        ));
    }
}
