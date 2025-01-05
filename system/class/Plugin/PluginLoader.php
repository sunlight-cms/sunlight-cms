<?php

namespace Sunlight\Plugin;

use Composer\Semver\Semver;
use Sunlight\Composer\Repository;
use Sunlight\Composer\RepositoryInjector;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Plugin\Type\PluginType;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;

class PluginLoader
{
    private const PLUGIN_DIR_PATTERN = '{' . Plugin::NAME_PATTERN . '$}AD';

    /** @var PluginConfigStore */
    private $configStore;
    /** @var array<string, PluginType> */
    private $types;

    /**
     * @param array<string, PluginType> $types
     */
    function __construct(PluginConfigStore $configStore, array $types)
    {
        $this->configStore = $configStore;
        $this->types = $types;
    }

    /**
     * Load plugin data from the filesystem
     *
     * @return array{
     *     plugins: array<string, PluginData>,
     *     autoload: array{
     *          "psr-0": array<string, string[]>,
     *          "psr-4": array<string, string[]>,
     *          classmap: array<string, string>,
     *          files: string[],
     *     },
     *     bound_files: string[],
     * }
     */
    function load(): array
    {
        $autoload = array_fill_keys(['psr-0', 'psr-4', 'classmap', 'files'], []);
        $boundFiles = [];
        $project = new Repository(SL_ROOT . 'composer.json');
        $composerInjector = new RepositoryInjector($project);

        $plugins = $this->findPlugins($project, $boundFiles);
        $this->resolveInstallationStatus($plugins);
        $plugins = $this->resolveDependencies($plugins, $composerInjector, $boundFiles);
        $this->resolveAutoload($plugins, $autoload);
        $this->resolveAutoloadForInjectedComposerPackages($composerInjector, $autoload);

        return [
            'plugins' => $plugins,
            'autoload' => $autoload,
            'bound_files' => $boundFiles,
        ];
    }

    /**
     * @return array<string, PluginData>
     */
    private function findPlugins(Repository $project, array &$boundFiles): array
    {
        $plugins = [];

        // load plugins from standard paths
        foreach ($this->types as $type) {
            $dir = SL_ROOT . $type->getDir();

            if (!is_dir($dir)) {
                continue;
            }

            foreach (Filesystem::createIterator($dir) as $item) {
                if (
                    $item->isDir()
                    && preg_match(self::PLUGIN_DIR_PATTERN, $item->getFilename())
                    && ($file = realpath($item->getPathname() . '/' . Plugin::FILE)) !== false
                ) {
                    $plugin = $this->loadPlugin(
                        $type,
                        $item->getFilename(),
                        $file,
                        "{$type->getDir()}/{$item->getFilename()}",
                        $boundFiles
                    );

                    $plugins[$plugin->id] = $plugin;
                }
            }
        }

        // load plugins from vendor/
        foreach ($project->getInstalledPackages() as $package) {
            if (!isset($package->extra->{'sunlight-plugins'}) || !is_object($package->extra->{'sunlight-plugins'})) {
                continue;
            }

            $packagePath = $project->getPackagePath($package);

            foreach ($package->extra->{'sunlight-plugins'} as $type => $vendorPlugins) {
                if (!isset($this->types[$type]) || !is_object($vendorPlugins)) {
                    continue;
                }

                foreach ($vendorPlugins as $name => $path) {
                    if (
                        preg_match(self::PLUGIN_DIR_PATTERN, $name)
                        && ($file = realpath("{$packagePath}/{$path}/" . Plugin::FILE)) !== false
                    ) {
                        $plugin = $this->loadPlugin(
                            $this->types[$type],
                            $name,
                            $file,
                            strncmp($file, SL_ROOT, strlen(SL_ROOT)) === 0
                                ? strtr(substr($file, strlen(SL_ROOT), -strlen(Plugin::FILE) - 1), DIRECTORY_SEPARATOR, '/')
                                : '#', // package outside of root
                            $boundFiles
                        );

                        $plugin->vendor = true;
                        $plugins[$plugin->id] = $plugin;
                    }
                }
            }
        }

        if (is_file($project->getInstalledJsonPath())) {
            $boundFiles[] = $project->getInstalledJsonPath();
        }

        return $plugins;
    }

    private function loadPlugin(PluginType $type, string $name, string $file, string $webPath, array &$boundFiles): PluginData
    {
        $plugin = new PluginData($type->getName(), "{$type->getName()}.{$name}", $name, $file, $webPath);

        // load options
        try {
            $plugin->options = Json::decode(file_get_contents($plugin->file));
        } catch (\InvalidArgumentException $e) {
            $plugin->errors[] = sprintf('could not parse %s - %s', $plugin->file, $e->getMessage());
        }

        // process options
        $optionsAreValid = false;

        if ($plugin->options !== null) {
            $type->resolveOptions($plugin, $plugin->options);

            if (!$plugin->hasErrors()) {
                $optionsAreValid = true;
                $this->checkEnvironment($plugin);
            }
        }

        // handle result
        if (!$plugin->hasErrors()) {
            // ok
            if ($plugin->status === null) {
                $plugin->status = Plugin::STATUS_OK;
            }
        } else {
            // there are errors
            $plugin->status = Plugin::STATUS_ERROR;

            if (!$optionsAreValid) {
                $type->resolveFallbackOptions($plugin);
            }
        }

        // override status if the plugin is disabled
        if ($this->configStore->hasFlag($plugin->id, 'disabled')) {
            $plugin->status = Plugin::STATUS_DISABLED;
        }

        // override status if the plugin is not allowed in safe mode
        if (Core::$safeMode && $plugin->status === Plugin::STATUS_OK && !$type->isPluginAllowedInSafeMode($plugin)) {
            $plugin->status = Plugin::STATUS_UNAVAILABLE;
        }

        $boundFiles[] = $file;

        return $plugin;
    }

    private function checkEnvironment(PluginData $plugin): void
    {
        $env = $plugin->options['environment'];

        // system version
        if (!$this->checkVersion($env['system'], Core::VERSION)) {
            $plugin->addError(sprintf('System version "%s" is required', $env['system']));
        }

        // PHP version
        if ($env['php'] !== null && !$this->checkVersion($env['php'], Environment::getPhpVersion())) {
            $plugin->addError(sprintf('PHP version "%s" is required', $env['php']));
        }

        // PHP extensions
        foreach ($env['php_extensions'] as $extension) {
            if (!extension_loaded($extension)) {
                $plugin->addError(sprintf('PHP extension "%s" is required', $extension));
            }
        }

        // DB engine
        if ($env['db_engine'] !== null && strcasecmp($env['db_engine'], DB::$engine) !== 0) {
            $plugin->addError(sprintf('Database engine "%s" is required', $env['db_engine']));
        }

        // debug mode
        if ($env['debug'] !== null && $env['debug'] !== Core::$debug) {
            $plugin->status = Plugin::STATUS_UNAVAILABLE;
        }
    }

    /**
     * Check version
     *
     * @param string $requiredVersion the required version pattern
     * @param string $actualVersion the version to match the pattern against
     */
    function checkVersion(string $requiredVersion, string $actualVersion): bool
    {
        return Semver::satisfies($actualVersion, $requiredVersion);
    }

    /**
     * Resolve plugin dependencies
     *
     * @param array<string, PluginData> $plugins
     * @return array<string, PluginData>
     */
    private function resolveDependencies(array $plugins, RepositoryInjector $composerInjector, array &$boundFiles): array
    {
        $sorted = [];
        $circularDependencyMap = $this->findCircularDependencies($plugins);

        while (!empty($plugins)) {
            $numAdded = 0;

            foreach ($plugins as $id => $plugin) {
                $delay = false;
                $errors = [];

                do {
                    // check state
                    if (!$plugin->isOk()) {
                        break;
                    }

                    // check circular dependencies
                    if (isset($circularDependencyMap[$id])) {
                        $errors[] = sprintf('circular dependency detected: "%s"', $circularDependencyMap[$id]);
                        break;
                    }

                    // check plugin dependencies
                    if (!empty($plugin->options['dependencies'])) {
                        $hasPendingDep = false;

                        foreach (array_keys($plugin->options['dependencies']) as $dependency) {
                            if (!isset($plugins[$dependency]) && !isset($sorted[$dependency])) {
                                $errors[] = sprintf('missing dependency "%s"', $dependency);
                            } elseif (!isset($sorted[$dependency])) {
                                $hasPendingDep = true;
                            }
                        }

                        if (!empty($errors)) {
                            break;
                        }

                        if ($hasPendingDep) {
                            // delay until dependency is processed
                            $delay = true;
                            break;
                        }

                        foreach ($plugin->options['dependencies'] as $dependency => $requiredVersion) {
                            $this->checkDependency($sorted[$dependency], $requiredVersion, $errors);
                        }

                        if (!empty($errors)) {
                            break;
                        }
                    }

                    // resolve composer dependencies
                    $this->resolveComposerDependencies($plugin, $composerInjector, $boundFiles, $errors);
                } while (false);

                // add unless delayed
                if (!$delay) {
                    if (!empty($errors)) {
                        $plugin->status = Plugin::STATUS_ERROR;
                        $plugin->errors = $errors;
                    }

                    $sorted[$id] = $plugin;
                    unset($plugins[$id]);
                    ++$numAdded;
                }
            }

            if ($numAdded === 0) {
                // this should not happen
                throw new \LogicException('Failed to resolve plugin dependencies');
            }
        }

        return $sorted;
    }

    /**
     * Find circular dependencies
     *
     * Returns a map:
     *
     * array(
     *      name1 => dependency_path_string1,
     *      ...
     * )
     *
     * @param array<string, PluginData> $plugins
     */
    private function findCircularDependencies(array $plugins): array
    {
        $circularDependencyMap = [];

        $checkQueue = [];

        foreach ($plugins as $id => $plugin) {
            if ($plugin->isOk()) {
                foreach (array_keys($plugin->options['dependencies']) as $dependency) {
                    $checkQueue[] = [$dependency, [$id => true, $dependency => true]];
                }
            }
        }

        while (!empty($checkQueue)) {
            [$id, $pathMap] = array_pop($checkQueue);

            if (isset($plugins[$id])) {
                foreach (array_keys($plugins[$id]->options['dependencies']) as $dependency) {
                    if (isset($pathMap[$dependency])) {
                        $pathString = "{$id}";

                        foreach (array_keys($pathMap) as $segment) {
                            $pathString .= " -> {$segment}";
                        }

                        $circularDependencyMap[$id] = $pathString;
                    } else {
                        $checkQueue[] = [$dependency, $pathMap + [$dependency => true]];
                    }
                }
            }
        }

        return $circularDependencyMap;
    }

    /**
     * Check plugin dependency version
     */
    private function checkDependency(PluginData $plugin, string $requiredVersion, array &$errors): void
    {
        if (!$plugin->isOk()) {
            $errors[] = sprintf('dependency "%s" is not available', $plugin->id);
        } elseif (!$this->checkVersion($requiredVersion, $plugin->options['version'])) {
            $errors[] = sprintf(
                'dependency "%s" (version "%s") is not compatible, version "%s" is required',
                $plugin->id,
                $plugin->options['version'],
                $requiredVersion
            );
        }
    }

    private function resolveComposerDependencies(
        PluginData $plugin,
        RepositoryInjector $injector,
        array &$boundFiles,
        array &$errors
    ): void {
        if (!$plugin->options['inject_composer'] || $plugin->vendor) {
            return;
        }

        $composerJsonPath = $plugin->dir . '/composer.json';

        if (is_file($composerJsonPath)) {
            $repository = new Repository($composerJsonPath);

            if (!is_dir($repository->getVendorPath())) {
                $errors[] = sprintf('composer dependencies are missing, try running "composer install" in %s', $repository->getDirectory());
                return;
            }

            if (!$injector->inject($repository, $errors)) {
                return;
            }

            $boundFiles[] = $repository->getComposerJsonPath();

            if (is_file($installedJsonPath = $repository->getInstalledJsonPath())) {
                $boundFiles[] = $installedJsonPath;
            }
        }
    }

    /**
     * @param array<string, PluginData> $plugins
     */
    private function resolveAutoload(array $plugins, array &$autoload): void
    {
        foreach ($plugins as $plugin) {
            if (!$plugin->isOk()) {
                continue;
            }

            $autoload['psr-4'][$plugin->options['namespace'] . '\\'] = $plugin->dir . DIRECTORY_SEPARATOR . 'class';

            foreach ($plugin->options['autoload'] as $type => $entries) {
                $autoload[$type] += $entries;
            }
        }
    }

    private function resolveAutoloadForInjectedComposerPackages(RepositoryInjector $injector, array &$autoload): void
    {
        $uniqueSources = [];
        $injectedPackages = $injector->getInjectedPackages();

        foreach ($injectedPackages as $package) {
            $source = $injector->getSource($package->name);
            $uniqueSources[spl_object_hash($source)] = $source;
        }

        // resolve for all unique sources
        foreach ($uniqueSources as $source) {
            $this->resolveAutoloadForComposerPackage(
                $autoload,
                $source->getDefinition(),
                $source->getDirectory(),
                $source->getClassMap()
            );
        }

        // resolve for all injected packages
        foreach ($injectedPackages as $package) {
            $source = $injector->getSource($package->name);

            $this->resolveAutoloadForComposerPackage(
                $autoload,
                $package,
                $source->getPackagePath($package),
                $source->getClassMap()
            );
        }
    }

    private function resolveAutoloadForComposerPackage(array &$autoload, \stdClass $package, string $packagePath, array $generatedClassMap): void
    {
        // PSR-0, PSR-4
        foreach (['psr-0', 'psr-4'] as $type) {
            if (empty($package->autoload->{$type})) {
                continue;
            }

            foreach ($package->autoload->{$type} as $prefix => $paths) {
                foreach ((array) $paths as $path) {
                    $autoload[$type][$prefix][] = Filesystem::normalizeWithBasePath($packagePath, $path);
                }
            }
        }

        // class map
        if (!empty($package->autoload->classmap)) {
            $this->resolveAutoloadForComposerPackageClassmap(
                $autoload,
                $packagePath,
                $package->autoload->classmap,
                $generatedClassMap
            );
        }

        // files
        if (!empty($package->autoload->files)) {
            foreach ($package->autoload->files as $path) {
                $autoload['files'][] = Filesystem::normalizeWithBasePath($packagePath, $path);
            }
        }
    }

    private function resolveAutoloadForComposerPackageClassmap(array &$autoload, string $packageDir, array $classMap, array $generatedClassMap): void
    {
        $prefixes = array_map(
            function ($path) use ($packageDir) {
                return Filesystem::parsePath(Filesystem::normalizeWithBasePath($packageDir, $path), true, true);
            },
            $classMap
        );

        $prefixLengths = array_map('strlen', $prefixes);

        foreach ($generatedClassMap as $className => $path) {
            $normalizedPath = Filesystem::normalizePath($path);

            foreach ($prefixes as $index => $prefix) {
                $prefixLength = $prefixLengths[$index];

                if (
                    // prefix matches
                    strncmp($prefix, $normalizedPath, $prefixLength) === 0
                    && (
                        !isset($normalizedPath[$prefixLength])      // path is equal to prefix
                        || $normalizedPath[$prefixLength] === '/'   // or continues into a directory
                    )
                ) {
                    $autoload['classmap'][$className] = $path;
                }
            }
        }
    }

    /**
     * Resolve installation status
     *
     * @param array<string, PluginData> $plugins
     */
    private function resolveInstallationStatus(array $plugins): void
    {
        foreach ($plugins as $plugin) {
            if ($plugin->isOk() && $plugin->options['installer'] !== null) {
                /** @var PluginInstaller $installer */
                $installer = require $plugin->options['installer'];
                $isInstalled = $installer->isInstalled();

                if (!$isInstalled) {
                    $plugin->status = Plugin::STATUS_NEEDS_INSTALLATION;
                }

                $plugin->installed = $isInstalled;
            }
        }
    }
}
