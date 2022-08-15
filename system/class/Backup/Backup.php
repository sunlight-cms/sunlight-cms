<?php

namespace Sunlight\Backup;

use Kuria\Options\Exception\ResolverException;
use Kuria\Options\Option;
use Kuria\Options\Resolver;
use Sunlight\Core;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;
use Sunlight\Util\TemporaryFile;
use Sunlight\Util\Zip;

/**
 * Backup archive
 */
class Backup
{
    /** @var \ZipArchive */
    private $zip;
    /** @var string */
    private $path;
    /** @var string */
    private $dataPath = 'data';
    /** @var string */
    private $dbDumpPath = 'database.sql';
    /** @var string|null */
    private $metadataPath = 'backup.json';
    /** @var string[] */
    private $directoryList = [];
    /** @var bool */
    private $open = false;
    /** @var bool */
    private $new = false;
    /** @var array|null */
    private $metadataCache;
    /** @var string[] */
    private $metadataErrors = [];
    /** @var TemporaryFile|null */
    private $dbDumpFile;
    /** @var string|null */
    private $dbDumpPrefix;
    /** @var string[] */
    private $fileList = [];

    function __construct(string $path)
    {
        $this->zip = new \ZipArchive();
        $this->path = $path;
    }

    function __destruct()
    {
        // revert unsaved changes
        if ($this->open) {
            $this->revertAndClose();
        }
    }

    public function getDataPath(): string
    {
        return $this->dataPath;
    }

    public function setDataPath(string $dataPath): void
    {
        $this->dataPath = $dataPath;
    }

    public function getDbDumpPath(): string
    {
        return $this->dbDumpPath;
    }

    public function setDbDumpPath(string $dbDumpPath): void
    {
        $this->dbDumpPath = $dbDumpPath;
    }

    public function getMetadataPath(): ?string
    {
        return $this->metadataPath;
    }

    public function setMetadataPath(?string $metadataPath): void
    {
        $this->metadataPath = $metadataPath;
    }

    /**
     * Create the backup
     *
     * @throws \RuntimeException on failure
     */
    function create(): void
    {
        if (($errorCode = $this->zip->open($this->path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) !== true) {
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
    function open(): void
    {
        Filesystem::ensureFileExists($this->path);

        if (($errorCode = $this->zip->open($this->path, \ZipArchive::CREATE)) !== true) {
            throw new \RuntimeException(sprintf('Could not open ZIP archive at "%s" (code %d)', $this->path, $errorCode));
        }

        $this->open = true;
        $this->new = false;
    }

    /**
     * Close the backup
     */
    function close(): void
    {
        if ($this->new && $this->metadataPath !== null) {
            $this->addMetaData();
        }

        $this->zip->close();
        $this->open = false;
    }

    /**
     * Revert the backup to its original state
     */
    function revert(): void
    {
        $this->zip->unchangeAll();
    }

    /**
     * Revert the backup to its original state and close it
     */
    function revertAndClose(): void
    {
        $this->revert();
        $this->close();
    }

    /**
     * Discard the backup
     */
    function discard(): void
    {
    }

    /**
     * Get the underlying ZIP archive for external modification
     */
    function getArchive(): \ZipArchive
    {
        $this->ensureOpenAndNew();

        return $this->zip;
    }

    /**
     * See if the backup is open
     */
    function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * See if the backup is new
     */
    function isNew(): bool
    {
        return $this->new;
    }

    /**
     * Add file or directory to the archive (recursively)
     *
     * @param string $path relative to the system root
     * @param callable|null $filter callback(data_path): bool
     * @param bool $addRootFileToFileList automatically add root files to the file list 1/0
     */
    function addPath(string $path, ?callable $filter = null, bool $addRootFileToFileList = true): void
    {
        $realPath = SL_ROOT . $path;

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
     * @param string $path relative to the system root
     * @param callable|null $filter callback(data_path): bool
     */
    function addDirectory(string $path, ?callable $filter = null): void
    {
        $this->ensureOpenAndNew();

        $basePath = SL_ROOT . $path;
        $rootPathInfo = new \SplFileInfo(SL_ROOT);
        $filePathNamePrefixLength = strlen($rootPathInfo->getPathname()) + 1;

        $iterator = Filesystem::createRecursiveIterator($basePath);

        foreach ($iterator as $item) {
            $dataPath = substr($item->getPathname(), $filePathNamePrefixLength);

            if ($filter !== null && !$filter($dataPath)) {
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
    function addEmptyDirectory(string $dataPath): void
    {
        $this->ensureOpenAndNew();

        $this->zip->addEmptyDir($this->dataPath . "/{$dataPath}");
    }

    /**
     * Add a file to the archive
     *
     * @param string $dataPath path within the backup's data directory (e.g. "foo.txt")
     * @param string $realPath real path to the file
     * @param callable|null $filter callback(data_path): bool
     * @param bool $addRootFileToFileList automatically add root files to the file list 1/0
     */
    function addFile(string $dataPath, string $realPath, ?callable $filter = null, bool $addRootFileToFileList = true): void
    {
        $this->ensureOpenAndNew();

        if ($filter === null || $filter($dataPath)) {
            // add files that are not in any directory to the file list
            if ($addRootFileToFileList && strpos($dataPath, '/') === false) {
                $this->fileList[] = $dataPath;
            }

            $this->zip->addFile(
                $realPath,
                $this->dataPath . "/{$dataPath}"
            );
        }
    }

    /**
     * Add file to the archive from a string
     *
     * @param string $dataPath path within the backup's data directory (e.g. "foo.txt)
     * @param string $data the file's contents
     * @param bool $addRootFileToFileList automatically add root files to the file list 1/0
     */
    function addFileFromString(string $dataPath, string $data, bool $addRootFileToFileList = true): void
    {
        $this->ensureOpenAndNew();

        if ($addRootFileToFileList && strpos($dataPath, '/') === false) {
            $this->fileList[] = $dataPath;
        }

        $this->zip->addFromString($this->dataPath . "/{$dataPath}", $data);
    }

    /**
     * See if the database contains a database dump
     */
    function hasDatabaseDump(): bool
    {
        $this->ensureOpenAndNotNew();

        return $this->zip->statName($this->dbDumpPath) !== false;
    }

    /**
     * Get database dump stream
     *
     * @return string|bool
     */
    function getDatabaseDump()
    {
        $this->ensureOpenAndNotNew();

        return $this->zip->getFromName($this->dbDumpPath);
    }

    /**
     * Get size of the database dump, if any
     */
    function getDatabaseDumpSize(): ?int
    {
        $this->ensureOpenAndNotNew();

        if ($this->hasDatabaseDump() && ($stat = $this->zip->statName($this->dbDumpPath))) {
            return $stat['size'];
        }

        return null;
    }

    /**
     * Add database dump
     */
    function addDatabaseDump(TemporaryFile $databaseDump, string $prefix): void
    {
        $this->ensureOpenAndNew();

        $this->dbDumpFile = $databaseDump; // keep a reference to the temp file
        $this->dbDumpPrefix = $prefix;
        $this->zip->addFile($databaseDump->getPathname(), $this->dbDumpPath);
    }

    /**
     * Extract one or more files into the given directory path
     *
     * @param array|string $files
     */
    function extractFiles($files, string $targetPath): void
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
     * @param string $targetPath path where to extract the directories to
     */
    function extractDirectories($directories, string $targetPath): void
    {
        $this->ensureOpenAndNotNew();

        Zip::extractDirectories(
            $this->zip,
            array_map([$this, 'dataPathToArchivePath'], $directories),
            $targetPath,
            ['exclude_prefix' => $this->dataPathToArchivePath('')]
        );
    }

    /**
     * Get total size of files and directories (excluding the database dump)
     */
    function getTotalDataSize(): int
    {
        $totalSize = 0;
        $dataPathPrefix = $this->dataPathToArchivePath('');
        $dataPathPrefixLength = strlen($dataPathPrefix);

        for ($i = 0; $i < $this->zip->numFiles; ++$i) {
            $stat = $this->zip->statIndex($i);

            if (strncmp($stat['name'], $dataPathPrefix, $dataPathPrefixLength) === 0) {
                $totalSize += $stat['size'];
            }
        }

        return $totalSize;
    }

    /**
     * @param string|null $key key to get from the metadata (null = all)
     * @throws \OutOfBoundsException if the key is invalid
     */
    function getMetaData(?string $key = null)
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
     * @return string[]
     */
    function getMetaDataErrors(): array
    {
        $this->ensureMetaDataLoaded();

        return $this->metadataErrors;
    }

    private function ensureMetaDataLoaded(): void
    {
        if ($this->metadataCache === null) {
            $this->loadMetaData();
        }
    }

    private function loadMetaData(): void
    {
        if ($this->metadataPath === null) {
            throw new \LogicException('No metadata path');
        }

        $stream = $this->zip->getStream($this->metadataPath);

        try {
            $this->metadataCache = $this->resolveMetadata(
                Json::decode(stream_get_contents($stream)),
                $this->metadataErrors
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not load meta data', 0, $e);
        }
    }

    private function resolveMetadata(array $metaData, array &$errors = null): ?array
    {
        $options = new Resolver();
        $options->addOption(
            Option::choice('system_version', Core::VERSION),
            Option::int('created_at'),
            Option::list('directory_list', 'string'),
            Option::list('file_list', 'string'),
            Option::string('db_prefix')->nullable(),
            Option::bool('is_patch')->default(false),
            Option::list('files_to_remove', 'string')->default([]),
            Option::list('directories_to_remove', 'string')->default([]),
            Option::list('directories_to_purge', 'string')->default([])
        );

        try {
            return $options->resolve($metaData)->toArray();
        } catch (ResolverException $e) {
            $errors = array_map('strval', $e->getErrors());

            return null;
        }
    }

    private function addMetaData(): void
    {
        $metaData = [
            'system_version' => Core::VERSION,
            'created_at' => time(),
            'directory_list' => $this->directoryList,
            'file_list' => $this->fileList,
            'db_prefix' => $this->dbDumpPrefix,
        ];

        $this->zip->addFromString($this->metadataPath, Json::encode($metaData));
    }

    /**
     * Ensure that the archive is new and open
     *
     * @throws \LogicException if the archive is not open or not new
     */
    private function ensureOpenAndNew(): void
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
    private function ensureOpenAndNotNew(): void
    {
        if (!$this->open) {
            throw new \LogicException('No archive has been opened');
        }
        if ($this->new) {
            throw new \LogicException('The backup has not been saved yet');
        }
    }

    private function dataPathToArchivePath(string $dataPath): string
    {
        return $this->dataPath . '/' . $dataPath;
    }
}
