<?php

namespace Sunlight\Plugin;

use Sunlight\Util\Filesystem;
use Sunlight\Util\Zip;

class PluginArchive
{
    /** Fail if any plugins already exist */
    const MODE_ALL_OR_NOTHING = 0;
    /** Skip any existing plugins */
    const MODE_SKIP_EXISTING = 1;
    /** Overwrite any existing plugins */
    const MODE_OVERWRITE_EXISTING = 2;

    /** @var PluginManager */
    private $manager;
    /** @var \ZipArchive */
    private $zip;
    /** @var string */
    private $path;
    /** @var bool */
    private $open = false;
    /** @var array<string, array<string, string>> type => name => archive path */
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
     * @param int $mode see PluginArchive::MODE_* constants
     * @param-out string[] $skippedPlugins list of skipped plugin paths
     * @return string[] list of successfully extracted plugin paths
     */
    function extract(int $mode, ?array &$skippedPlugins = null): array
    {
        $toExtract = [];
        $skippedPlugins = [];

        // get and check plugins
        foreach ($this->getPlugins() as $type => $plugins) {
            foreach ($plugins as $name => $archivePath) {
                if (
                    $mode !== self::MODE_OVERWRITE_EXISTING
                     && (
                        $this->manager->getPlugins()->hasName($type, $name)
                        || $this->manager->getPlugins()->hasInactiveName($type, $name)
                     )
                ) {
                    $skippedPlugins[] = $archivePath;
                } else {
                    $toExtract[] = $archivePath;
                }
            }
        }

        // abort if there is nothing to do or we should fail on existing
        if (empty($toExtract) || $mode === self::MODE_ALL_OR_NOTHING && !empty($skippedPlugins)) {
            return [];
        }

        // if overwriting existing plugins, empty their directories first
        if ($mode === self::MODE_OVERWRITE_EXISTING) {
            foreach ($this->getPlugins() as $type => $plugins) {
                foreach ($plugins as $name => $archivePath) {
                    $existingPlugin = $this->manager->getPlugins()->getByName($type, $name)
                        ?? $this->manager->getPlugins()->getInactiveByName($type, $name);

                    if ($existingPlugin !== null) {
                        Filesystem::emptyDirectory($existingPlugin->getDirectory());
                    }
                }
            }
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

    /**
     * Get information about plugins inside the archive
     * 
     * @return array<string, array<string, string>> type => name => archive path
     */
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

            if (($errorCode = $this->zip->open($this->path)) !== true) {
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
        $plugins = [];

        // map types
        $dirPatterns = [];
        $typeDir2Type = [];

        foreach ($this->manager->getTypes() as $type) {
            $dirPatterns[] = preg_quote($type->getDir());
            $typeDir2Type[$type->getDir()] = $type->getName();
        }

        // build the regex
        $regex = '{(' . implode('|', $dirPatterns) . ')/(' . Plugin::NAME_PATTERN . ')/(.+)$}AD';

        // iterate all files in the archive
        for ($i = 0; $i < $this->zip->numFiles; ++$i) {
            $stat = $this->zip->statIndex($i);

            if (preg_match($regex, $stat['name'], $match)) {
                [, $dir, $name, $subpath] = $match;
                $type = $typeDir2Type[$dir];

                if (!isset($plugins[$type][$name])) {
                    $plugins[$type][$name] = [
                        'name' => $name,
                        'path' => $dir . '/' . $name,
                        'valid' => false,
                    ];
                }

                if ($subpath === Plugin::FILE) {
                    $plugins[$type][$name]['valid'] = true;
                }
            }
        }

        // map valid plugins
        $this->plugins = array_map(
            function (array $plugins) {
                // remove extra data
                return array_column(
                    // remove invalid plugins
                    array_filter($plugins, function (array $plugin) { return $plugin['valid']; }),
                    'path',
                    'name'
                );
            },
            $plugins
        );
    }
}
