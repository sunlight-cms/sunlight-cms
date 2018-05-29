<?php

namespace Sunlight\Backup;

use Kuria\Cache\Util\TemporaryFile;
use Sunlight\Core;
use Sunlight\Option\OptionSet;
use Sunlight\Option\OptionSetNormalizerException;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;
use Sunlight\Util\Zip;
use ZipArchive;

/**
 * Backup archive
 */
class Backup
{
    /** Database dump file path */
    const DB_DUMP_PATH = 'database.sql';
    /** Metadata file path */
    const METADATA_PATH = 'backup.json';
    /** Data path (prefix) */
    const DATA_PATH = 'data';

    /** @var ZipArchive */
    protected $zip;
    /** @var string */
    protected $path;
    /** @var array */
    protected $directoryList = array();
    /** @var TemporaryFile[] */
    protected $temporaryFiles = array();
    /** @var bool */
    protected $open = false;
    /** @var bool */
    protected $new = false;
    /** @var array|null */
    protected $metadataCache;
    /** @var array|null */
    protected $metadataErrors;
    /** @var string|null */
    protected $addedDbDumpPrefix;
    /** @var string[] */
    protected $fileList = array();

    /**
     * @param string $path
     */
    function __construct($path)
    {
        $this->zip = new ZipArchive();
        $this->path = $path;
    }

    /**
     * Destructor
     */
    function __destruct()
    {
        if ($this->open) {
            $this->revertAndClose();
        }

        $this->discardTemporaryFiles();
    }

    /**
     * Create the backup
     *
     * @throws \RuntimeException on failure
     */
    function create()
    {
        if (($errorCode = $this->zip->open($this->path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) !== true) {
            throw new \RuntimeException(sprintf('Could not create ZIP archive at "%s" (code %d)', $this->path, $errorCode));
        }

        $this->open = true;
        $this->new = true;
    }

    /**
     * Open the backup
     *
     * @throws \RuntimeException if the file is not found or is not a valid ZIP file
     */
    function open()
    {
        \Sunlight\Util\Filesystem::ensureFileExists($this->path);

        if (($errorCode = $this->zip->open($this->path, ZipArchive::CREATE)) !== true) {
            throw new \RuntimeException(sprintf('Could not open ZIP archive at "%s" (code %d)', $this->path, $errorCode));
        }

        $this->open = true;
        $this->new = false;
    }

    /**
     * Close the backup
     */
    function close()
    {
        if ($this->new) {
            $this->setMetaData();
        }

        $this->zip->close();
        $this->open = false;
        
        $this->discardTemporaryFiles();
    }

    /**
     * Revert the backup to its original state
     */
    function revert()
    {
        $this->zip->unchangeAll();
    }

    /**
     * Revert the backup to its original state and close it
     */
    function revertAndClose()
    {
        $this->revert();
        $this->close();
    }

    /**
     * Discard the backup
     */
    function discard()
    {
        if ($this->open) {
            $this->revertAndClose();
        }
        if (is_file($this->path)) {
            unlink($this->path);
        }
        $this->discardTemporaryFiles();
    }

    /**
     * Discard temporary files
     */
    protected function discardTemporaryFiles()
    {
        foreach ($this->temporaryFiles as $tmpFile) {
            $tmpFile->discard();
        }

        $this->temporaryFiles = array();
    }

    /**
     * Get the underlying ZIP archive for external modification
     *
     * @return ZipArchive
     */
    function getArchive()
    {
        $this->ensureOpenAndNew();

        return $this->zip;
    }

    /**
     * See if the backup is open
     *
     * @return bool
     */
    function isOpen()
    {
        return $this->open;
    }

    /**
     * See if the backup is new
     *
     * @return bool
     */
    function isNew()
    {
        return $this->new;
    }

    /**
     * Add file or directory to the archive (recursively)
     *
     * @param string        $path                  relative to the system root
     * @param callable|null $filter                callback(data_path): bool
     * @param bool          $addRootFileToFileList automatically add root files to the file list 1/0
     */
    function addPath($path, $filter = null, $addRootFileToFileList = true)
    {
        $realPath = _root . $path;

        if (file_exists($realPath)) {
            if (is_dir($realPath)) {
                $this->addDirectory($path, $filter);
            } else {
                $this->addFile($path, $realPath, $filter, $addRootFileToFileList);
            }
        }
    }

    /**
     * Recursively add a directory to the archive
     *
     * @param string        $path   relative to the system root
     * @param callable|null $filter callback(data_path): bool
     */
    function addDirectory($path, $filter = null)
    {
        $this->ensureOpenAndNew();

        $basePath = _root . $path;
        $rootPathInfo = new \SplFileInfo(_root);
        $filePathNamePrefixLength = strlen($rootPathInfo->getPathname()) + 1;

        $iterator = Filesystem::createRecursiveIterator($basePath);

        foreach ($iterator as $item) {
            $dataPath = substr($item->getPathname(), $filePathNamePrefixLength);

            if ($filter !== null && !call_user_func($filter, $dataPath)) {
                continue;
            }

            if ($item->isDir()) {
                if (Filesystem::isDirectoryEmpty($item->getPathname())) {
                    $this->addEmptyDirectory($dataPath);
                }
            } else {
                $this->addFile(
                    $dataPath,
                    $item->getPathname()
                );
            }
        }

        $this->directoryList[] = $path;
    }

    /**
     * Add empty directory to the archive
     *
     * @param string $dataPath path within the backup's data directory (e.g. "foo/bar")
     */
    function addEmptyDirectory($dataPath)
    {
        $this->ensureOpenAndNew();

        $this->zip->addEmptyDir(static::DATA_PATH . "/{$dataPath}");
    }

    /**
     * Add a file to the archive
     *
     * @param string        $dataPath              path within the backup's data directory (e.g. "foo.txt")
     * @param string        $realPath              real path to the file
     * @param callable|null $filter                callback(data_path): bool
     * @param bool          $addRootFileToFileList automatically add root files to the file list 1/0
     */
    function addFile($dataPath, $realPath, $filter = null, $addRootFileToFileList = true)
    {
        $this->ensureOpenAndNew();

        if ($filter === null || call_user_func($filter, $dataPath)) {
            // add files that are not in any directory to the file list
            if ($addRootFileToFileList && strpos($dataPath, '/') === false) {
                $this->fileList[] = $dataPath;
            }

            $this->zip->addFile(
                $realPath,
                static::DATA_PATH . "/{$dataPath}"
            );
        }
    }

    /**
     * Add file to the archive from a string
     *
     * @param string $dataPath              path within the backup's data directory (e.g. "foo.txt)
     * @param string $data                  the file's contents
     * @param bool   $addRootFileToFileList automatically add root files to the file list 1/0
     */
    function addFileFromString($dataPath, $data, $addRootFileToFileList = true)
    {
        $this->ensureOpenAndNew();

        if ($addRootFileToFileList && strpos($dataPath, '/') === false) {
            $this->fileList[] = $dataPath;
        }

        $this->zip->addFromString(static::DATA_PATH . "/{$dataPath}", $data);
    }

    /**
     * See if the database contains a database dump
     *
     * @return bool
     */
    function hasDatabaseDump()
    {
        $this->ensureOpenAndNotNew();

        return $this->zip->statName(static::DB_DUMP_PATH) !== false;
    }

    /**
     * Get database dump stream
     *
     * @return resource|bool
     */
    function getDatabaseDump()
    {
        $this->ensureOpenAndNotNew();

        return $this->zip->getStream(static::DB_DUMP_PATH);
    }

    /**
     * Add database dump
     *
     * @param TemporaryFile $databaseDump
     * @param string        $prefix
     */
    function addDatabaseDump(TemporaryFile $databaseDump, $prefix)
    {
        $this->ensureOpenAndNew();

        $this->zip->addFile($databaseDump->getPathname(), static::DB_DUMP_PATH);
        $this->addedDbDumpPrefix = $prefix;
        $this->temporaryFiles[] = $databaseDump;
    }

    /**
     * Extract one or more files into the given directory path
     *
     * @param $files
     * @param $targetPath
     */
    function extractFiles($files, $targetPath)
    {
        $this->ensureOpenAndNotNew();

        foreach ((array) $files as $file) {
            Zip::extractFile(
                $this->zip,
                $this->dataPathToArchivePath($file),
                "{$targetPath}/{$file}"
            );
        }
    }

    /**
     * Extract one or more directories into the given directory path
     *
     * The entire path is preserved.
     * Existing files will be overwritten.
     *
     * @param array|string $directories one or more archive paths relative to the data directory (e.g. "upload")
     * @param string       $targetPath  path where to extract the directories to
     */
    function extractDirectories($directories, $targetPath)
    {
        $this->ensureOpenAndNotNew();

        Zip::extractDirectories(
            $this->zip,
            array_map(array($this, 'dataPathToArchivePath'), $directories),
            $targetPath,
            array('exclude_prefix' => $this->dataPathToArchivePath(''))
        );
    }

    /**
     * @param string $key key to get from the metadata (null = all)
     * @throws \OutOfBoundsException if the key is invalid
     * @return mixed
     */
    function getMetaData($key = null)
    {
        $this->ensureOpenAndNotNew();
        $this->ensureMetaDataLoaded();

        if ($key !== null) {
            if (!array_key_exists($key, $this->metadataCache)) {
                throw new \OutOfBoundsException(sprintf('Unknown metadata key "%s"', $key));
            }
            
            return $this->metadataCache[$key];
        }

        return $this->metadataCache;
    }

    /**
     * @return array
     */
    function getMetaDataErrors()
    {
        $this->ensureMetaDataLoaded();

        return $this->metadataErrors;
    }

    protected function ensureMetaDataLoaded()
    {
        if ($this->metadataCache === null) {
            $this->loadMetaData();
        }
    }

    protected function loadMetaData()
    {
        $stream = $this->zip->getStream(static::METADATA_PATH);

        try {
            $this->metadataCache = Json::decode(stream_get_contents($stream));
            $this->validateMetaData($this->metadataCache, $this->metadataErrors);
        } catch (\Exception $e) {
            throw new \RuntimeException('Could not load meta data', 0, $e);
        }
    }

    /**
     * @param array      $metaData
     * @param array|null $errors
     * @return bool
     */
    protected function validateMetaData(array &$metaData, array &$errors = null)
    {
        $optionSet = new OptionSet(array(
            'system_version' => array('type' => 'string', 'required' => true, 'normalizer' => function ($value) {
                if (Core::VERSION !== $value) {
                    throw new OptionSetNormalizerException('incompatible system version');
                }
            }),
            'created_at' => array('type' => 'integer', 'required' => true),
            'directory_list' => array('type' => 'array', 'default' => array()),
            'file_list' => array('type' => 'array', 'default' => array()),
            'db_prefix' => array('type' => 'string', 'nullable' => true, 'default' => null),
            'is_patch' => array('type' => 'boolean', 'default' => false),
            'files_to_remove' => array('type' => 'array', 'default' => array()),
            'directories_to_remove' => array('type' => 'array', 'default' => array()),
            'directories_to_purge' => array('type' => 'array', 'default' => array()),
        ));

        return $optionSet->process($metaData, null, $errors);
    }

    protected function setMetaData()
    {
        $metaData = array(
            'system_version' => Core::VERSION,
            'created_at' => time(),
            'directory_list' => $this->directoryList,
            'file_list' => $this->fileList,
            'db_prefix' => $this->addedDbDumpPrefix,
        );

        $this->zip->addFromString(static::METADATA_PATH, Json::encode($metaData, true));
    }

    /**
     * Ensure that the archive is new and open
     *
     * @throws \LogicException if the archive is not open or not new
     */
    protected function ensureOpenAndNew()
    {
        if (!$this->open) {
            throw new \LogicException('No archive has been opened');
        }
        if (!$this->new) {
            throw new \LogicException('Existing backups cannot be modified');
        }
    }

    /**
     * Ensure that the archive is NOT new and open
     *
     * @throws \LogicException if the archive is not open or not new
     */
    protected function ensureOpenAndNotNew()
    {
        if (!$this->open) {
            throw new \LogicException('No archive has been opened');
        }
        if ($this->new) {
            throw new \LogicException('The backup has not been saved yet');
        }
    }

    /**
     * @param string $dataPath
     * @return string
     */
    protected function dataPathToArchivePath($dataPath)
    {
        return static::DATA_PATH . '/' . $dataPath;
    }
}
