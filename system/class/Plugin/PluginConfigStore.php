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

    function hasFlag(string $id, string $flag): bool
    {
        return is_file($this->getFilePath($id, $flag));
    }

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

    function getConfigFile(string $id, array $defaults): ConfigurationFile
    {
        return new ConfigurationFile($this->getFilePath($id, 'php'), $defaults);
    }

    function cleanup(PluginRegistry $plugins): void
    {
        foreach (Filesystem::createIterator($this->storePath) as $item) {
            if ($item->isFile()) {
                $id = $this->getIdFromFilename($item->getFilename());

                if (!$plugins->has($id) && !$plugins->hasInactive($id)) {
                    unlink($item->getPathname());
                }
            }
        }
    }

    private function getFilePath(string $id, string $ext): string
    {
        return $this->storePath . '/' . $this->getFilename($id, $ext);
    }

    private function getFilename(string $id, string $ext): string
    {
        return strtr($id, '/', '$') . '.' . $ext;
    }

    private function getIdFromFilename(string $filename): string
    {
        return strtr(pathinfo($filename, PATHINFO_FILENAME), '$', '/');
    }
}
