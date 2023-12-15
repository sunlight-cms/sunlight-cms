<?php

namespace Sunlight\Plugin;

use Sunlight\Util\ConfigurationFile;
use Sunlight\Util\Filesystem;

class PluginConfigStore
{
    /** @var string */
    private $storePath;

    function __construct()
    {
        $this->storePath = SL_ROOT . 'plugins/config';
    }

    /**
     * Check plugin flag
     */
    function hasFlag(string $id, string $flag): bool
    {
        return is_file($this->getFilePath($id, $flag));
    }

    /**
     * Set plugin flag
     */
    function setFlag(string $id, string $flag, bool $status): void
    {
        $path = $this->getFilePath($id, $flag);

        if ($status !== is_file($path)) {
            if ($status) {
                touch($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * Get configuration file
     */
    function getConfigFile(string $id, array $defaults): ConfigurationFile
    {
        return new ConfigurationFile($this->getFilePath($id, 'php'), $defaults);
    }

    /**
     * Remove files that don't belong to any plugin
     */
    function cleanup(PluginRegistry $plugins): void
    {
        foreach ($this->iterateFiles() as $file) {
            $id = $this->getIdFromFilename($file->getFilename());

            if (!$plugins->has($id) && !$plugins->hasInactive($id)) {
                unlink($file->getPathname());
            }
        }
    }

    /**
     * @return iterable<\SplFileInfo>
     */
    private function iterateFiles(): iterable
    {
        foreach (Filesystem::createIterator($this->storePath) as $item) {
            if ($item->isFile() && $item->getFilename() !== '.gitkeep') {
                yield $item;
            }
        }
    }

    private function getFilePath(string $id, string $ext): string
    {
        return $this->storePath . '/' . $this->getFilename($id, $ext);
    }

    private function getFilename(string $id, string $ext): string
    {
        return $id . '.' . $ext;
    }

    private function getIdFromFilename(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }
}
