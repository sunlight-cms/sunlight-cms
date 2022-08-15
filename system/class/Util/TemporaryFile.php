<?php

namespace Sunlight\Util;

/**
 * Temporary file
 *
 * - extension of SplFileInfo designed to deal with temporary files
 * - the temporary file is removed when the object goes out of scope or on script shutdown
 * - this happens even if the script ends with an uncaught exception or a fatal error
 */
class TemporaryFile extends \SplFileInfo
{
    /** @var string */
    private $realPath;
    /** @var bool */
    private $valid = true;
    /** @var bool */
    private $keep = false;

    /**
     * @param string|null $fileName existing file name or null to generate
     * @param string|null $tmpDir existing temporary directory or null to use the system's default
     * @throws \RuntimeException if the file cannot be created
     */
    function __construct(?string $fileName = null, ?string $tmpDir = null)
    {
        if ($fileName === null && ($fileName = tempnam($tmpDir ?: sys_get_temp_dir(), '')) === false) {
            throw new \RuntimeException('Unable to create temporary file');
        }

        parent::__construct($fileName);

        // store real path now (cwd might change during shutdown)
        $this->realPath = $this->getRealPath();
    }

    function __destruct()
    {
        if (!$this->keep) {
            $this->remove();
        }
    }

    /**
     * Move the temporary file to another location
     *
     * - the moved file will not be removed anymore
     * - the temporary file will no longer be valid after this operation
     *
     * @param string $newFilePath new file path (including filename)
     * @param bool $createPath create the path if it does not exist 1/0
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
                $this->valid = false;
            }

            return $success;
        }

        return false;
    }

    /**
     * Remove the temporary file immediately
     */
    function remove(): bool
    {
        if ($this->valid) {
            $removed = !is_file($this->realPath) || @unlink($this->realPath);

            if ($removed) {
                $this->valid = false;
            }

            return $removed;
        }

        return false;
    }

    /**
     * Keep the temporary file
     *
     * Calling {@see remove()} explicitly will still remove the file unless it was moved.
     */
    function keep(): void
    {
        $this->keep = true;
    }
}
