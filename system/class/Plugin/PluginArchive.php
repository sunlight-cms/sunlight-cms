<?php

namespace Sunlight\Plugin;

use Sunlight\Util\Filesystem;
use Sunlight\Util\Zip;

class PluginArchive
{
    /** @var PluginManager */
    protected $manager;
    /** @var \ZipArchive */
    protected $zip;
    /** @var string */
    protected $path;
    /** @var bool */
    protected $open = false;
    /**
     * type => array(
     *      name1 => array(
     *          path => string,
     *          valid => bool,
     *      ),
     *      ...
     * )
     *
     * @var array|null
     */
    protected $plugins;

    /**
     * @param PluginManager $manager
     * @param string        $path
     */
    function __construct(PluginManager $manager, string $path)
    {
        $this->manager = $manager;
        $this->zip = new \ZipArchive();
        $this->path = $path;
    }

    /**
     * Extract the archive
     *
     * @param bool          $merge          merge with current plugins (only install new ones) 1/0
     * @param string[]|null &$failedPlugins
     * @return string[] list of successfully extracted plugins
     */
    function extract(bool $merge = false, ?array &$failedPlugins = null): array
    {
        $toExtract = [];
        $failedPlugins = [];

        // get and check plugins
        foreach ($this->getPlugins() as $type => $plugins) {
            foreach ($plugins as $pluginId => $pluginParams) {
                if ($this->manager->exists($type, $pluginId)) {
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
        Zip::extractDirectories($this->zip, $toExtract, _root);

        return $toExtract;
    }

    /**
     * See if the archive contains any plugins
     *
     * @return bool
     */
    function hasPlugins(): bool
    {
        $this->ensureOpen();

        return !empty($this->plugins);
    }

    /**
     * @return array
     */
    function getPlugins(): array
    {
        $this->ensureOpen();

        return $this->plugins;
    }

    /**
     * Ensure that the archive is open
     */
    protected function ensureOpen(): void
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
    protected function load(): void
    {
        $this->plugins = [];

        // map types
        // build the regex
        $dirPatterns = [];
        $typeDir2Type = [];
        foreach ($this->manager->getTypes() as $type) {
            $definition = $this->manager->getTypeDefinition($type);

            $dirPatterns[] = preg_quote($definition['dir']);
            $typeDir2Type[$definition['dir']] = $type;
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

                if (Plugin::FILE === $subpath) {
                    $this->plugins[$type][$name]['valid'] = true;
                }
            }
        }
    }
}
