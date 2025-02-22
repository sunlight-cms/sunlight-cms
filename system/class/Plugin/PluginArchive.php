<?php

namespace Sunlight\Plugin;

use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;
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
        $dirsToExtract = [];
        $targetPathMap = [];
        $extractedPlugins = [];
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
                    $skippedPlugins[] = $type . '/' . $name;
                } else {
                    $dirsToExtract[] = $archivePath;
                    $targetPathMap[$archivePath] = SL_ROOT . $this->manager->getType($type)->getDir() . '/' . $name;
                    $extractedPlugins[] = $type . '/' . $name;
                }
            }
        }

        // abort if there is nothing to do or we should fail on existing
        if (empty($dirsToExtract) || $mode === self::MODE_ALL_OR_NOTHING && !empty($skippedPlugins)) {
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
        Zip::extractDirectories($this->zip, $dirsToExtract, $targetPathMap, ['path_mode' => Zip::PATH_SUB]);

        return $extractedPlugins;
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

        // find all plugin.json files in the archive
        /** @var array<array{index: int, name: string}> $pluginJsons */
        $pluginJsons = [];

        for ($i = 0; $i < $this->zip->numFiles; ++$i) {
            $stat = $this->zip->statIndex($i);

            if ($stat === false) {
                throw new \RuntimeException(sprintf('Failed to stat index %d in the archive', $i));
            }

            if (($stat['name'][-1] ?? null) !== '/' && basename($stat['name']) === Plugin::FILE) {
                $pluginJsons[] = $stat;
            }
        }

        // sort by path length
        usort($pluginJsons, function (array $a, array $b) {
            return strlen($a['name']) <=> strlen($b['name']);
        });

        // remove plugin.json paths that are nested
        $pluginJsonsCount = count($pluginJsons);

        for ($i = 0; $i < $pluginJsonsCount; ++$i) {
            // skip removed entries
            if (!isset($pluginJsons[$i])) {
                continue;
            }

            // for every $i, remove all following entries that are under the same path
            $pathEndOffset = strrpos($pluginJsons[$i]['name'], '/') + 1;

            for ($j = $i + 1; isset($pluginJsons[$j]); ++$j) {
                if (strncasecmp($pluginJsons[$i]['name'], $pluginJsons[$j]['name'], $pathEndOffset) === 0) {
                    unset($pluginJsons[$j]);
                }
            }
        }

        // load plugins
        foreach ($pluginJsons as $pluginJson) {
            $this->loadPlugin($plugins, $pluginJson['index'], $pluginJson['name']);
        }

        $this->plugins = $plugins;
    }

    /**
     * @param array<string, array<string, string>> $plugins
     */
    private function loadPlugin(array &$plugins, int $pluginJsonIndex, string $pluginJsonPath): void
    {
        try {
            if (strpos($pluginJsonPath, '/') === false) {
                throw new \RuntimeException(sprintf('%s at the root of the archive is not supported', Plugin::FILE));
            }

            $pluginName = basename(dirname($pluginJsonPath));
            $typeName = $this->guessPluginTypeFromPath($pluginJsonPath)
                ?? $this->guessPluginTypeFromSchema($pluginJsonIndex);

            $plugins[$typeName][$pluginName] = dirname($pluginJsonPath);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf('Could not load plugin at "%s" from the archive: %s', dirname($pluginJsonPath), $e->getMessage()),
                0,
                $e
            );
        }
    }

    private function guessPluginTypeFromPath(string $pluginJsonPath): ?string
    {
        if (preg_match('{plugins/(\w+)/' . Plugin::NAME_PATTERN . '/' . preg_quote(Plugin::FILE) . '$}AD', $pluginJsonPath, $match)) {
            $type = $this->manager->getType($match[1]);

            if ($type !== null) {
                return $type->getName();
            }
        }

        return null;
    }

    private function guessPluginTypeFromSchema(int $pluginJsonIndex): string
    {
        $pluginJson = $this->zip->getFromIndex($pluginJsonIndex);

        if ($pluginJson === false) {
            throw new \RuntimeException(sprintf('Could not load %s', Plugin::FILE));
        }

        $pluginJson = Json::decode($pluginJson);

        if (!isset($pluginJson['$schema'])) {
            throw new \RuntimeException(sprintf(
                '%s must define "$schema" or be in a standard path (plugins/<type>/<name>/)',
                Plugin::FILE
            ));
        }

        if (!is_string($pluginJson['$schema'])) {
            throw new \RuntimeException(sprintf(
                '"$schema" in %s must be a string, got %s',
                Plugin::FILE,
                gettype($pluginJson['$schema'])
            ));
        }

        $type = $this->manager->getType(pathinfo($pluginJson['$schema'], PATHINFO_FILENAME));

        if ($type === null) {
            throw new \RuntimeException(sprintf(
                'Failed to determine plugin type from "$schema": "%s" in %s',
                $pluginJson['$schema'],
                Plugin::FILE
            ));
        }

        return $type->getName();
    }
}
