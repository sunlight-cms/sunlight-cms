<?php

namespace Sunlight\Util;

/**
 * Temporary file
 *
 * An extension of SplFileInfo designed to deal with temporary files.
 * The temporary file is always removed at the end of the request,
 * even if the script ends with an uncaught exception or a fatal error.
 */
class TemporaryFile extends \SplFileInfo
{
    /** @var array */
    private static $registry = [];
    /** @var bool */
    private static $removalOnShutdown = false;

    /** @var string */
    private $realPath;
    /** @var bool */
    private $valid = true;

    /**
     * @param string|null $fileName existing file name or null to generate
     * @param string|null $tmpDir   existing temporary directory or null to use the system's default
     * @throws \RuntimeException if the file cannot be created
     */
    function __construct(?string $fileName = null, ?string $tmpDir = null)
    {
        // generate a file name
        if ($fileName === null && ($fileName = tempnam($tmpDir ?: sys_get_temp_dir(), '')) === false) {
            throw new \RuntimeException('Unable to create temporary file');
        }

        // make sure the discardAll method is called on shutdown
        self::ensureRemovalOnShutdown();

        // call parent constructor
        parent::__construct($fileName);

        $this->realPath = $this->getRealPath();

        // add path to registry
        self::$registry[$this->realPath] = true;
    }

    /**
     * Make sure the discardAll method is called on shutdown
     */
    private static function ensureRemovalOnShutdown(): void
    {
        if (!self::$removalOnShutdown) {
            register_shutdown_function([__CLASS__, 'discardAll']);
            self::$removalOnShutdown = true;
        }
    }

    /**
     * Move the temporary file to another location
     *
     * - the moved file will not be removed on shutdown
     * - the temporary file will no longer be valid after this operation
     *
     * @param string $newFilePath new file path (including filename)
     * @param bool   $createPath  create the path if it does not exist 1/0
     * @return bool
     */
    function move(string $newFilePath, bool $createPath = true): bool
    {
        if ($this->valid) {
            if ($createPath && !is_dir($directoryPath = dirname($newFilePath))) {
                @mkdir($directoryPath, 0777, true);
            }

            @chmod($this->realPath, 0666);

            $success = @rename($this->realPath, $newFilePath);

            if ($success) {
                $this->unregister();
                $this->valid = false;
            }

            return $success;
        }

        return false;
    }

    /**
     * Discard the temporary file immediately
     *
     * @return bool
     */
    function discard(): bool
    {
        if ($this->valid) {
            $removed = is_file($this->realPath)
                ? @unlink($this->realPath)
                : true;

            if ($removed) {
                $this->unregister();
                $this->valid = false;
            }

            return $removed;
        }

        return false;
    }

    /**
     * Unregister the temporary file
     *
     *  - unregistered temporary files are not automatically removed on shutdown
     *  - calling {@see discard()} will still remove the file if it exists
     */
    function unregister(): void
    {
        unset(self::$registry[$this->realPath]);
    }

    /**
     * Discard all temporary files
     *
     * This method is automatically called on shutdown.
     */
    static function discardAll(): void
    {
        foreach (array_keys(self::$registry) as $realPath) {
            if (is_file($realPath)) {
                @unlink($realPath);
            }
        }

        self::$registry = [];
    }
}
