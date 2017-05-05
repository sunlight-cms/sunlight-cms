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
    /** @var string|null */
    protected $addedDbDumpPrefix;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->zip = new ZipArchive();
        $this->path = $path;
    }

    /**
     * Destructor
     */
    public function __destruct()
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
    public function create()
    {
        if (true !== ($errorCode = $this->zip->open($this->path, ZipArchive::CREATE | ZipArchive::OVERWRITE))) {
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
    public function open()
    {
        _ensureFileExists($this->path);

        if (true !== ($errorCode = $this->zip->open($this->path, ZipArchive::CREATE))) {
            throw new \RuntimeException(sprintf('Could not open ZIP archive at "%s" (code %d)', $this->path, $errorCode));
        }

        $this->open = true;
        $this->new = false;
    }

    /**
     * Close the backup
     */
    public function close()
    {
        if ($this->new) {
            $this->addMetaData();
        }

        $this->zip->close();
        $this->open = false;
        
        $this->discardTemporaryFiles();
    }

    /**
     * Revert the backup to its original state
     */
    public function revert()
    {
        $this->zip->unchangeAll();
    }

    /**
     * Revert the backup to its original state and close it
     */
    public function revertAndClose()
    {
        $this->revert();
        $this->close();
    }

    /**
     * Discard the backup
     */
    public function discard()
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
     * See if the backup is open
     *
     * @return bool
     */
    public function isOpen()
    {
        return $this->open;
    }

    /**
     * See if the backup is new
     *
     * @return bool
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Add file or directory to the archive (recursively)
     *
     * @param string        $path   relative to the system root
     * @param callable|null $filter callback(data_path): bool
     */
    public function addPath($path, $filter = null)
    {
        $realPath = _root . $path;

        if (file_exists($realPath)) {
            if (is_dir($realPath)) {
                $this->addDirectory($path, $filter);
            } else {
                $this->addFile($path, $realPath, $filter);
            }
        }
    }

    /**
     * Recursively add a directory to the archive
     *
     * @param string        $path   relative to the system root
     * @param callable|null $filter callback(data_path): bool
     */
    public function addDirectory($path, $filter = null)
    {
        $this->ensureOpenAndNew();

        $basePath = _root . $path;
        $rootPathInfo = new \SplFileInfo(_root);
        $filePathNamePrefixLength = strlen($rootPathInfo->getPathname()) + 1;

        $iterator = Filesystem::createRecursiveIterator($basePath);

        foreach ($iterator as $item) {
            $dataPath = substr($item->getPathname(), $filePathNamePrefixLength);

            if (null !== $filter && !call_user_func($filter, $dataPath)) {
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
    public function addEmptyDirectory($dataPath)
    {
        $this->ensureOpenAndNew();

        $this->zip->addEmptyDir(static::DATA_PATH . "/{$dataPath}");
    }

    /**
     * Add a file to the archive
     *
     * @param string        $dataPath path within the backup's data directory (e.g. "foo.txt")
     * @param string        $realPath real path to the file
     * @param callable|null $filter   callback(data_path): bool
     */
    public function addFile($dataPath, $realPath, $filter = null)
    {
        $this->ensureOpenAndNew();

        if (null === $filter || call_user_func($filter, $dataPath)) {
            $this->zip->addFile(
                $realPath,
                static::DATA_PATH . "/{$dataPath}"
            );
        }
    }

    /**
     * Add file to the archive from a string
     *
     * @param string $dataPath path within the backup's data directory (e.g. "foo.txt)
     * @param string $data     the file's contents
     */
    public function addFileFromString($dataPath, $data)
    {
        $this->ensureOpenAndNew();

        $this->zip->addFromString(static::DATA_PATH . "/{$dataPath}", $data);
    }

    /**
     * See if the database contains a database dump
     *
     * @return bool
     */
    public function hasDatabaseDump()
    {
        $this->ensureOpenAndNotNew();

        return false !== $this->zip->statName(static::DB_DUMP_PATH);
    }

    /**
     * Get database dump stream
     *
     * @return resource|bool
     */
    public function getDatabaseDump()
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
    public function addDatabaseDump(TemporaryFile $databaseDump, $prefix)
    {
        $this->ensureOpenAndNew();

        $this->zip->addFile($databaseDump->getPathname(), static::DB_DUMP_PATH);
        $this->addedDbDumpPrefix = $prefix;
        $this->temporaryFiles[] = $databaseDump;
    }

    /**
     * Extract one or more directories to the given path
     *
     * The entire path is preserved.
     * Existing files will be overwritten.
     *
     * @param array|string $dataPaths  one or more archive paths relative to the data directory (e.g. "upload")
     * @param string       $targetPath path where to extract the directories to
     */
    public function extract($dataPaths, $targetPath)
    {
        $this->ensureOpenAndNotNew();
        
        $prefix = static::DATA_PATH . '/';

        $archivePaths = array();
        foreach ((array) $dataPaths as $dataPath) {
            $archivePaths[] = $prefix . $dataPath;
        }

        Zip::extractPaths($this->zip, $archivePaths, $targetPath, array(
            'exclude_prefix' => $prefix,
        ));
    }

    /**
     * Get metadata
     *
     * @param string $key key to get from the metadata (null = all)
     * @throws \OutOfBoundsException if the key is invalid
     * @return mixed
     */
    public function getMetaData($key = null)
    {
        $this->ensureOpenAndNotNew();

        if (null === $this->metadataCache) {
            $stream = $this->zip->getStream(static::METADATA_PATH);
            try {
                $this->metadataCache = Json::decode(stream_get_contents($stream));
            } catch (\Exception $e) {
                fclose($stream);

                throw $e;
            }
        }

        if (null !== $key) {
            if (!array_key_exists($key, $this->metadataCache)) {
                throw new \OutOfBoundsException(sprintf('Unknown metadata key "%s"', $key));
            }
            
            return $this->metadataCache[$key];
        }

        return $this->metadataCache;
    }

    /**
     * Validate meta data
     *
     * @param array      $metaData
     * @param array|null &$errors
     * @return bool
     */
    public function validateMetaData(array $metaData, &$errors = null)
    {
        $optionSet = new OptionSet(array(
            'system_version' => array('type' => 'string', 'required' => true, 'normalizer' => function ($value) {
                if (Core::VERSION !== $value) {
                    throw new OptionSetNormalizerException('incompatible system version');
                }
            }),
            'system_state' => array('type' => 'string', 'required' => true, 'normalizer' => function ($value) {
                if (Core::STATE !== $value) {
                    throw new OptionSetNormalizerException('incompatible system state');
                }
            }),
            'created_at' => array('type' => 'integer', 'required' => true),
            'directory_list' => array('type' => 'array', 'required' => true),
            'db_prefix' => array('type' => 'string', 'nullable' => true, 'required' => true),
        ));

        return $optionSet->process($metaData, $errors);
    }

    /**
     * Add meta data
     */
    protected function addMetaData()
    {
        $metaData = array(
            'system_version' => Core::VERSION,
            'system_state' => Core::STATE,
            'created_at' => time(),
            'directory_list' => $this->directoryList,
            'db_prefix' => $this->addedDbDumpPrefix,
        );

        $this->zip->addFromString(static::METADATA_PATH, Json::encode($metaData));
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
}
