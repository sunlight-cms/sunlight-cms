<?php

namespace Sunlight\Plugin;

use Composer\Semver\Semver;
use Sunlight\Composer\Repository;
use Sunlight\Composer\RepositoryInjector;
use Sunlight\Core;
use Sunlight\Plugin\Type\PluginType;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;

class PluginLoader
{
    /** @var PluginType[] */
    private $types;
    /** @var string */
    private $pluginIdPattern;
    /**
     * @param PluginType[] $types
     */
    function __construct(array $types)
    {
        $this->types = $types;
        $this->pluginIdPattern = '{' . Plugin::ID_PATTERN . '$}AD';
    }

    /**
     * Load plugin data from the filesystem
     *
     * Returns an array with the following structure:
     *
     *      array(
     *          plugins => array(
     *              type => array(name => data, ...)
     *              ...
     *          )
     *          autoload => array(
     *              psr-0 => array(prefix => paths, ...)
     *              psr-4 => array(prefix => paths, ...)
     *              classmap => array(className => path, ...)
     *              files => array(path, ...)
     *          )
     *          bound_files => array(path, ...)
     *      )
     *
     * @param bool $resolveInstallationStatus
     * @return array
     */
    function load(bool $resolveInstallationStatus = true): array
    {
        $autoload = array_fill_keys(['psr-0', 'psr-4', 'classmap', 'files'], []);
        $boundFiles = [];

        $composerInjector = new RepositoryInjector(new Repository(realpath(SL_ROOT . '/composer.json')));
        $typeNames = array_keys($this->types);

        $plugins = $this->findPlugins($boundFiles);

        // resolve dependencies
        foreach ($typeNames as $typeName) {
            $plugins[$typeName] = $this->resolveDependencies($plugins[$typeName]);
        }

        // resolve autoload
        foreach ($typeNames as $typeName) {
            $this->resolveAutoload($plugins[$typeName], $autoload);
        }

        foreach ($typeNames as $typeName) {
            $this->handleComposerRepositories($plugins[$typeName], $boundFiles, $composerInjector);
        }

        $this->resolveAutoloadForInjectedComposerPackages($autoload, $composerInjector);

        // resolve installation status
        if ($resolveInstallationStatus) {
            foreach ($this->types as $typeName => $type) {
                $this->resolveInstallationStatus($plugins[$typeName]);
            }
        }

        return [
            'plugins' => $plugins,
            'autoload' => $autoload,
            'bound_files' => $boundFiles,
        ];
    }

    /**
     * @return PluginData[][]
     */
    private function findPlugins(array &$boundFiles): array
    {
        $plugins = [];

        // load plugins from standard paths
        foreach ($this->types as $typeName => $type) {
            $plugins[$typeName] = [];

            $dir = SL_ROOT . $type->getDir();

            // scan directory
            foreach (scandir($dir) as $item) {
                // validate item
                if (
                    preg_match($this->pluginIdPattern, $item) // skips dots and invalid names
                    && is_dir("{$dir}/{$item}")
                    && is_file($pluginFile = "{$dir}/{$item}/" . Plugin::FILE)
                ) {
                    // load plugin
                    $boundFiles[] = $pluginFile;

                    $plugins[$typeName][$item] = $this->loadPlugin($item, $pluginFile, $type);
                }
            }
        }

        return $plugins;
    }

    function loadPlugin(string $id, string $pluginFile, PluginType $type): PluginData
    {
        $plugin = new PluginData(
            $id,
            $type->getName(),
            realpath($pluginFile),
            $type->getDir() . '/' . $id
        );

        // check state
        $isDisabled = is_file($plugin->dir . '/' . Plugin::DEACTIVATING_FILE);

        // load options
        try {
            $options = Json::decode(file_get_contents($plugin->file));
        } catch (\RuntimeException $e) {
            $options = null;
            $plugin->errors[] = sprintf('could not parse %s - %s', $plugin->file, $e->getMessage());
        }

        // process options
        if ($options !== null) {
            $type->resolveOptions($plugin, $options);

            if (!$plugin->hasErrors()) {
                $this->validatePlugin($plugin);
            }
        }

        // handle result
        if (!$plugin->hasErrors()) {
            // ok
            $plugin->status = Plugin::STATUS_OK;
        } else {
            // there are errors
            $plugin->status = Plugin::STATUS_HAS_ERRORS;
            $type->resolveFallbackOptions($plugin);
        }

        // resolve plugin class
        $plugin->options['class'] = $this->resolvePluginClass($plugin, $type);

        // override status if the plugin is disabled
        if ($isDisabled) {
            $plugin->status = Plugin::STATUS_DISABLED;
        }

        return $plugin;
    }

    private function validatePlugin(PluginData $plugin): void
    {
        // api version
        if (!$this->checkVersion($plugin->options['api'], Core::VERSION)) {
            $plugin->addError(sprintf('API version "%s" is not compatible with system version "%s"', $plugin->options['api'], Core::VERSION));
        }

        // PHP version
        if ($plugin->options['php'] !== null && !version_compare($plugin->options['php'], PHP_VERSION, '<=')) {
            $plugin->addError(sprintf('PHP version "%s" or newer is required', $plugin->options['php']));
        }

        // extensions
        foreach ($plugin->options['extensions'] as $extension) {
            if (!extension_loaded($extension)) {
                $plugin->addError(sprintf('PHP extension "%s" is required', $extension));
            }
        }

        // debug mode
        if ($plugin->options['debug'] !== null && $plugin->options['debug'] !== Core::$debug) {
            $plugin->addError(
                $plugin->options['debug']
                    ? 'debug mode is required'
                    : 'production mode is required'
            );
        }
    }

    private function resolvePluginClass(PluginData $plugin, PluginType $type): string
    {
        $specifiedClass = $plugin->options['class'];

        if ($specifiedClass === null) {
            // no class specified - use default class of the given type
            return $type->getClass();
        }

        if (strpos($specifiedClass, '\\') === false) {
            // plain (unnamespaced) class name specified - prefix by plugin namespace
            return $plugin->options['namespace'] . '\\' . $specifiedClass;
        }

        // fully-qualified class name
        return $specifiedClass;
    }

    /**
     * Check version
     *
     * @param string $requiredVersion the required version pattern
     * @param string $actualVersion   the version to match the pattern against
     * @return bool
     */
    function checkVersion(string $requiredVersion, string $actualVersion): bool
    {
        return Semver::satisfies($actualVersion, $requiredVersion);
    }

    /**
     * @param PluginData $plugin
     * @param string[] $errors
     */
    private function convertPluginToErrorState(PluginData $plugin, array $errors): void
    {
        $plugin->status = Plugin::STATUS_HAS_ERRORS;
        $plugin->errors = $errors;
    }

    /**
     * Resolve plugin dependencies
     *
     * @param PluginData[] $plugins
     * @throws \RuntimeException if the dependencies cannot be resolved
     * @return array
     */
    private function resolveDependencies(array $plugins): array
    {
        $sorted = [];
        $circularDependencyMap = $this->findCircularDependencies($plugins);

        while (!empty($plugins)) {
            $numAdded = 0;
            foreach ($plugins as $id => $plugin) {
                $canBeAdded = true;
                $errors = [];

                if ($plugin->isOk()) {
                    if (isset($circularDependencyMap[$id])) {
                        // the plugin is in a circular dependency chain
                        $errors[] = sprintf('circular dependency detected: "%s"', $circularDependencyMap[$id]);
                        $canBeAdded = false;
                    } elseif (!empty($plugin->options['requires'])) {
                        foreach ($plugin->options['requires'] as $dependency => $requiredVersion) {
                            if (isset($sorted[$dependency])) {
                                // the dependency is already in the sorted map
                                if (!$this->checkDependency($sorted[$dependency], $requiredVersion, $errors)) {
                                    $canBeAdded = false;
                                }
                            } else {
                                // not in the sorted map yet
                                if (isset($plugins[$dependency])) {
                                    $this->checkDependency($plugins[$dependency], $requiredVersion, $errors);
                                } else {
                                    $errors[] = sprintf('missing dependency "%s"', $dependency);
                                }

                                $canBeAdded = false;
                            }
                        }
                    }
                }

                // add if all dependencies are ok or there are errors
                if ($canBeAdded || !empty($errors)) {
                    if (!empty($errors)) {
                        $this->convertPluginToErrorState($plugin, $errors);
                    }

                    $sorted[$id] = $plugin;
                    unset($plugins[$id]);
                    ++$numAdded;
                }
            }

            if ($numAdded === 0) {
                // this should not happen
                throw new \RuntimeException('Could not resolve plugin dependencies');
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
     * @param PluginData[] $plugins
     * @return array
     */
    private function findCircularDependencies(array $plugins): array
    {
        $circularDependencyMap = [];

        $checkQueue = [];
        foreach ($plugins as $id => $plugin) {
            if ($plugin->isOk()) {
                foreach (array_keys($plugin->options['requires']) as $dependency) {
                    $checkQueue[] = [$dependency, [$id => true, $dependency => true]];
                }
            }
        }

        while (!empty($checkQueue)) {
            [$id, $pathMap] = array_pop($checkQueue);

            if (isset($plugins[$id])) {
                foreach (array_keys($plugins[$id]->options['requires']) as $dependency) {
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
     *
     * @param PluginData $plugin
     * @param string $requiredVersion
     * @param array &$errors
     * @return bool
     */
    private function checkDependency(PluginData $plugin, string $requiredVersion, array &$errors): bool
    {
        if ($plugin->status !== Plugin::STATUS_OK) {
            $errors[] = sprintf('dependency "%s" is not available', $plugin->id);
            
            return false;
        }

        if (!$this->checkVersion($requiredVersion, $plugin->options['version'])) {
            $errors[] = sprintf(
                'dependency "%s" (version "%s") is not compatible, version "%s" is required',
                $plugin->id,
                $plugin->options['version'],
                $requiredVersion
            );

            return false;
        }

        return true;
    }

    /**
     * @param PluginData[] $plugins
     */
    private function resolveAutoload(array $plugins, array &$autoload): void
    {
        foreach ($plugins as $plugin) {
            if ($plugin->status !== Plugin::STATUS_OK) {
                continue;
            }

            $autoload['psr-4'][$plugin->options['namespace'] . '\\'] = $plugin->dir;

            foreach ($plugin->options['autoload'] as $type => $entries) {
                $autoload[$type] += $entries;
            }
        }
    }

    private function handleComposerRepositories(array $plugins, array &$boundFiles, RepositoryInjector $injector): void
    {
        foreach ($plugins as $plugin) {
            if (!$plugin->options['inject_composer']) {
                continue;
            }

            $composerJsonPath = $plugin->dir . '/composer.json';

            if (is_file($composerJsonPath)) {
                $repository = new Repository($composerJsonPath);

                if (!is_dir($repository->getVendorPath())) {
                    $this->convertPluginToErrorState(
                        $plugin,
                        [sprintf('missing dependencies, please run "composer install" in %s', $repository->getDirectory())]
                    );
                    continue;
                }

                $this->ensureComposerRepositoryAccessControl($repository);

                if (!$injector->inject($repository, $errors)) {
                    $this->convertPluginToErrorState($plugin, $errors);
                    continue;
                }

                $boundFiles[] = $repository->getComposerJsonPath();

                if (is_file($installedJsonPath = $repository->getInstalledJsonPath())) {
                    $boundFiles[] = $installedJsonPath;
                }
            }
        }
    }

    private function ensureComposerRepositoryAccessControl(Repository $repository): void
    {
        if (!is_file($repository->getVendorPath() . '/.htaccess')) {
            Filesystem::denyAccessToDirectory($repository->getVendorPath());
        }
    }

    private function resolveAutoloadForInjectedComposerPackages(array &$autoload, RepositoryInjector $injector): void
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
            foreach($package->autoload->files as $path) {
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
     * @param PluginData[] $plugins
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
