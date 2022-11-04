<?php

namespace Sunlight\Plugin;

use Sunlight\Util\Filesystem;
use Sunlight\Util\Zip;

class PluginArchive
{
    /** @var PluginManager */
    private $manager;
    /** @var \ZipArchive */
    private $zip;
    /** @var string */
    private $path;
    /** @var bool */
    private $open = false;
    /** @var array<string, array<string, array{path: string, valid: bool}> */
    private $plugins;

    function __construct(PluginManager $manager, string $path)
    {
        $this->manager = $manager;
        $this->zip = new \ZipArchive();
        $this->path = $path;
    }

    /**
     * Extract the archive
     *
     * @param bool $merge merge with current plugins (only install new ones) 1/0
     * @param string[]|null &$failedPlugins
     * @return string[] list of successfully extracted plugins
     */
    function extract(bool $merge = false, ?array &$failedPlugins = null): array
    {
        $toExtract = [];
        $failedPlugins = [];

        // get and check plugins
        foreach ($this->getPlugins() as $type => $plugins) {
            foreach ($plugins as $name => $pluginParams) {
                if (
                    $this->manager->getPlugins()->hasName($type, $name)
                    || $this->manager->getPlugins()->hasInactiveName($type, $name)
                ) {
                    $failedPlugins[] = $pluginParams['path'];
                } else {
                    $toExtract[] = $pluginParams['path'];
                }
            }
        }

        // abort if there are failed plugins and we are not merging
        if (!$merge && !empty($failedPlugins)) {
            return [];
        }

        // extract plugins
        Zip::extractDirectories($this->zip, $toExtract, SL_ROOT);

        return $toExtract;
    }

    /**
     * See if the archive contains any plugins
     */
    function hasPlugins(): bool
    {
        $this->ensureOpen();

        return !empty($this->plugins);
    }

    function getPlugins(): array
    {
        $this->ensureOpen();

        return $this->plugins;
    }

    /**
     * Ensure that the archive is open
     */
    private function ensureOpen(): void
    {
        if (!$this->open) {
            Filesystem::ensureFileExists($this->path);

            if (($errorCode = $this->zip->open($this->path, \ZipArchive::CREATE)) !== true) {
                throw new \RuntimeException(sprintf('Could not open ZIP archive at "%s" (code %d)', $this->path, $errorCode));
            }

            $this->load();

            $this->open = true;
        }
    }

    /**
     * Load the archive
     */
    private function load(): void
    {
        $this->plugins = [];

        // map types
        // build the regex
        $dirPatterns = [];
        $typeDir2Type = [];

        foreach ($this->manager->getTypes() as $type) {
            $dirPatterns[] = preg_quote($type->getDir());
            $typeDir2Type[$type->getDir()] = $type->getName();
        }

        $regex = '{(' . implode('|', $dirPatterns) . ')/(' . Plugin::ID_PATTERN . ')/(.+)$}AD';

        // iterate all files in the archive
        for ($i = 0; $i < $this->zip->numFiles; ++$i) {
            $stat = $this->zip->statIndex($i);

            if (preg_match($regex, $stat['name'], $match)) {
                [, $dir, $name, $subpath] = $match;
                $type = $typeDir2Type[$dir];

                if (!isset($this->plugins[$type][$name])) {
                    $this->plugins[$type][$name] = [
                        'path' => $dir . '/' . $name,
                        'valid' => false,
                    ];
                }

                if ($subpath === Plugin::FILE) {
                    $this->plugins[$type][$name]['valid'] = true;
                }
            }
        }
    }
}
