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
    /** @var callable|null */
    private $metadataFactory;
    /** @var string[] */
    private $directoryList = [];
    /** @var string[] */
    private $fileList = [];
    /** @var bool */
    private $open = false;
    /** @var bool */
    private $new = false;
    /** @var array|null */
    private $metadataCache;
    /** @var string[] */
    private $metadataErrors = [];
    /** @var array|null */
    private $addedMetaData;
    /** @var TemporaryFile|null */
    private $dbDumpFile;
    /** @var string|null */
    private $dbDumpPrefix;

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

    function getPath(): string
    {
        return $this->path;
    }

    function getDataPath(): string
    {
        return $this->dataPath;
    }

    function setDataPath(string $dataPath): void
    {
        $this->dataPath = $dataPath;
    }

    function getDbDumpPath(): string
    {
        return $this->dbDumpPath;
    }

    function setDbDumpPath(string $dbDumpPath): void
    {
        $this->dbDumpPath = $dbDumpPath;
    }

    function getMetadataPath(): ?string
    {
        return $this->metadataPath;
    }

    function setMetadataPath(?string $metadataPath): void
    {
        $this->metadataPath = $metadataPath;
    }

    function getMetadataFactory(): ?callable
    {
        return $this->metadataFactory;
    }

    function setMetadataFactory(?callable $metadataFactory): void
    {
        $this->metadataFactory = $metadataFactory;
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

        if (($errorCode = $this->zip->open($this->path)) !== true) {
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
     */
    function addPath(string $path, ?callable $filter = null): void
    {
        $realPath = SL_ROOT . $path;

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
     * @param string $path relative to the system root
     * @param callable|null $filter callback(data_path): bool
     */
    function addDirectory(string $path, ?callable $filter = null): void
    {
        $this->ensureOpenAndNew();

        $basePath = SL_ROOT . $path;
        $iterator = Filesystem::createRecursiveIterator($basePath);

        if ($iterator->valid()) {
            $rootPathInfo = new \SplFileInfo(SL_ROOT);
            $filePathNamePrefixLength = strlen($rootPathInfo->getPathname()) + 1;

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
                    $this->addFile($dataPath, $item->getPathname(), null, false);
                }
            }
        } else {
            $this->addEmptyDirectory($path);
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
     * @param bool $addToList add to the file list 1/0
     */
    function addFile(string $dataPath, string $realPath, ?callable $filter = null, bool $addToList = true): void
    {
        $this->ensureOpenAndNew();

        if ($filter === null || $filter($dataPath)) {
            if ($addToList) {
                $this->fileList[] = $dataPath;
            }

            $this->zip->addFile($realPath, $this->dataPath . "/{$dataPath}");
        }
    }

    /**
     * Add file to the archive from a string
     *
     * @param string $dataPath path within the backup's data directory (e.g. "foo.txt)
     * @param string $data the file's contents
     * @param callable|null $filter callback(data_path): bool
     * @param bool $addToList add to the file list 1/0
     */
    function addFileFromString(string $dataPath, string $data, ?callable $filter = null, bool $addToList = true): void
    {
        $this->ensureOpenAndNew();

        if ($filter === null || $filter($dataPath)) {
            if ($addToList) {
                $this->fileList[] = $dataPath;
            }
    
            $this->zip->addFromString($this->dataPath . "/{$dataPath}", $data);
        }
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
     * Get content of any file from the backup
     *
     * @param string $file path to a file in the archive
     */
    function getFile(string $file): ?string
    {
        $this->ensureOpenAndNotNew();

        $data = $this->zip->getFromName($file);

        return $data !== false ? $data : null;
    }

    /**
     * Extract one or more files from into the given directory path
     *
     * @param string[] $files file paths relative to the data directory
     */
    function extractFiles($files, string $targetPath): void
    {
        $this->ensureOpenAndNotNew();

        foreach ($files as $file) {
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

    /**
     * Get metadata that was added to a new backup - after calling close()
     */
    function getAddedMetaData(): ?array
    {
        return $this->addedMetaData;
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
            Option::node(
                'patch',
                Option::string('new_system_version'),
                Option::list('files_to_remove', 'string')->default([]),
                Option::list('directories_to_remove', 'string')->default([]),
                Option::list('directories_to_purge', 'string')->default([]),
                Option::list('patch_scripts', 'string')->default([])
            )->default(null)
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
        $metadata = [
            'system_version' => Core::VERSION,
            'created_at' => time(),
            'directory_list' => $this->directoryList,
            'file_list' => $this->fileList,
            'db_prefix' => $this->dbDumpPrefix,
        ];

        if ($this->metadataFactory !== null) {
            $metadata = ($this->metadataFactory)($metadata);
        }

        $this->addedMetaData = $metadata;
        $this->zip->addFromString($this->metadataPath, Json::encode($metadata));
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
