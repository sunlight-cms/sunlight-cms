<?php

namespace Sunlight\Plugin;

use Composer\Semver\Semver;
use Sunlight\Composer\Repository;
use Sunlight\Composer\RepositoryInjector;
use Sunlight\Core;
use Sunlight\Option\OptionSet;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;
use Sunlight\Util\StringManipulator;

class PluginLoader
{
    /** @var array */
    private $types;
    /** @var string */
    private $pluginIdPattern;
    /** @var OptionSet */
    private $commonOptionSet;
    /** @var OptionSet[] */
    private $typeOptionSets;

    /**
     * @param array $types
     */
    function __construct(array $types)
    {
        $this->types = $types;
        $this->pluginIdPattern = '{' . Plugin::ID_PATTERN . '$}AD';

        $this->commonOptionSet = new OptionSet(Plugin::$commonOptions);
        $this->commonOptionSet->setIgnoreExtraIndexes(true);

        $this->typeOptionSets = [];
        foreach ($types as $type => $typeDefinition) {
            $this->typeOptionSets[$type] = new OptionSet($typeDefinition['options']);
            $this->typeOptionSets[$type]->addKnownIndexes($this->commonOptionSet->getIndexes());
        }
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
        $plugins = [];
        $autoload = array_fill_keys(['psr-0', 'psr-4', 'classmap', 'files'], []);
        $boundFiles = [];

        $composerInjector = new RepositoryInjector(new Repository(realpath(_root . '/composer.json')));
        $typeNames = array_keys($this->types);

        $this->findPlugins($plugins, $boundFiles);

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

    private function findPlugins(array &$plugins, array &$boundFiles): void
    {
        // load plugins from standard paths
        foreach ($this->types as $typeName => $type) {
            $plugins[$typeName] = [];

            $dir = _root . $type['dir'];

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

                    $plugins[$typeName][$item] = $this->loadPlugin(
                        $this->createPluginData(
                            $item,
                            $pluginFile,
                            $type['dir'] . '/' . $item,
                            $typeName
                        )
                    );
                }
            }
        }
    }

    /**
     * @param array $plugin
     * @return array
     */
    function loadPlugin(array $plugin): array
    {
        $type = $this->types[$plugin['type']];
        $context = $this->createPluginOptionContext($plugin, $type);

        // check state
        $isDisabled = is_file($plugin['dir'] . '/' . Plugin::DEACTIVATING_FILE);

        // load options
        try {
            $options = Json::decode(file_get_contents($plugin['file']));
        } catch (\RuntimeException $e) {
            $options = null;
            $plugin['errors'][] = sprintf('could not parse %s - %s', $plugin['file'], $e->getMessage());
        }

        // process options
        if ($options !== null) {
            // set defaults
            $options += $plugin['options'];

            // common options
            $this->commonOptionSet->process($options, $context, $plugin['definition_errors']);

            // type-specific options
            if (empty($plugin['definition_errors'])) {
                $this->typeOptionSets[$plugin['type']]->process($options, $context, $plugin['definition_errors']);
            }

            $this->validateOptions($options, $plugin['definition_errors'], $plugin['errors']);
        }

        // handle result
        if (!$this->hasErrors($plugin)) {
            // ok
            $plugin['status'] = Plugin::STATUS_OK;
            $plugin['options'] = $options;
        } else {
            // there are errors
            $plugin['status'] = Plugin::STATUS_HAS_ERRORS;
            if ($options === null || !empty($plugin['definition_errors'])) {
                $options = [
                    'id' => $plugin['id'],
                    'name' => $plugin['id'],
                    'version' => '0.0.0',
                    'api' => '0.0.0',
                ];
                $this->commonOptionSet->process($options, $context);
            }
            $plugin['options'] = $options;
        }

        // resolve plugin class
        $plugin['options']['class'] = $this->resolvePluginClass($plugin, $type);

        // override status if the plugin is disabled
        if ($isDisabled) {
            $plugin['status'] = Plugin::STATUS_DISABLED;
        }

        return $plugin;
    }

    /**
     * Validate options
     *
     * @param array $options
     * @param array $definitionErrors
     * @param array $errors
     */
    private function validateOptions(array $options, array $definitionErrors, array &$errors): void
    {
        // api version
        if (!isset($definitionErrors['api']) && !$this->checkVersion($options['api'], Core::VERSION)) {
            $errors[] = sprintf('API version "%s" is not compatible with system version "%s"', $options['api'], Core::VERSION);
        }

        // PHP version
        if (!isset($definitionErrors['php']) && $options['php'] !== null && !version_compare($options['php'], PHP_VERSION, '<=')) {
            $errors[] = sprintf('PHP version "%s" or newer is required', $options['php']);
        }

        // extensions
        if (!isset($definitionErrors['extensions'])) {
            foreach ($options['extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    $errors[] = sprintf('PHP extension "%s" is required', $extension);
                }
            }
        }

        // debug mode
        if (!isset($definitionErrors['debug']) && $options['debug'] !== null && $options['debug'] !== _debug) {
            $errors[] = $options['debug']
                ? 'debug mode is required'
                : 'production mode is required';
        }
    }

    /**
     * @param array $plugin
     * @return bool
     */
    private function hasErrors(array $plugin): bool
    {
        return $plugin['errors'] || $plugin['definition_errors'];
    }

    /**
     * @param array $plugin
     * @param array $type
     * @return string
     */
    private function resolvePluginClass(array $plugin, array $type): string
    {
        $specifiedClass = $plugin['options']['class'];

        if ($specifiedClass === null) {
            // no class specified - use default class of the given type
            return $type['class'];
        }

        if (strpos($specifiedClass, '\\') === false) {
            // plain (unnamespaced) class name specified - prefix by plugin namespace
            return $plugin['options']['namespace'] . '\\' . $specifiedClass;
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
     * @param string $id
     * @param string $file
     * @param string|null $webPath
     * @param string $typeName
     * @param array $defaultOptions
     * @return array
     */
    private function createPluginData(string $id, string $file, ?string $webPath, string $typeName): array
    {
        $file = realpath($file);

        return [
            'id' => $id,
            'camel_id' => StringManipulator::toCamelCase($id),
            'type' => $typeName,
            'status' => null,
            'installed' => null,
            'dir' => dirname($file),
            'file' => $file,
            'web_path' => $webPath,
            'errors' => [],
            'definition_errors' => [],
            'options' => ['name' => $id],
        ];
    }

    /**
     * @param array $plugin
     * @param array $type
     * @return array
     */
    private function createPluginOptionContext(array &$plugin, array $type): array
    {
        return [
            'plugin' => &$plugin,
            'type' => $type,
        ];
    }

    /**
     * @param array $plugin
     * @param array $errors
     * @return array
     */
    private function convertPluginToErrorState(array $plugin, array $errors): array
    {
        return ['status' => Plugin::STATUS_HAS_ERRORS, 'errors' => $errors] + $plugin;
    }

    /**
     * Resolve plugin dependencies
     *
     * @param array $plugins
     * @throws \RuntimeException if the dependencies cannot be resolved
     * @return array
     */
    private function resolveDependencies(array $plugins): array
    {
        $sorted = [];
        $circularDependencyMap = $this->findCircularDependencies($plugins);

        while (!empty($plugins)) {
            $numAdded = 0;
            foreach ($plugins as $name => $plugin) {
                $canBeAdded = true;
                $errors = [];

                if (Plugin::STATUS_OK === $plugin['status']) {
                    if (isset($circularDependencyMap[$name])) {
                        // the plugin is in a circular dependency chain
                        $errors[] = sprintf('circular dependency detected: "%s"', $circularDependencyMap[$name]);
                        $canBeAdded = false;
                    } elseif (!empty($plugin['options']['requires'])) {
                        foreach ($plugin['options']['requires'] as $dependency => $requiredVersion) {
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

                // add if all dependencies are ok
                if ($canBeAdded) {
                    $sorted[$name] = $plugin;
                } elseif (!empty($errors)) {
                    $sorted[$name] = $this->convertPluginToErrorState($plugin, $errors);
                }

                if ($canBeAdded || !empty($errors)) {
                    unset($plugins[$name]);
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
     * @param array $plugins
     * @return array
     */
    private function findCircularDependencies(array $plugins): array
    {
        $circularDependencyMap = [];

        $checkQueue = [];
        foreach ($plugins as $name => $plugin) {
            if (Plugin::STATUS_OK === $plugin['status']) {
                foreach (array_keys($plugin['options']['requires']) as $dependency) {
                    $checkQueue[] = [$dependency, [$name => true, $dependency => true]];
                }
            }
        }

        while (!empty($checkQueue)) {
            [$name, $pathMap] = array_pop($checkQueue);

            if (isset($plugins[$name])) {
                foreach (array_keys($plugins[$name]['options']['requires']) as $dependency) {
                    if (isset($pathMap[$dependency])) {
                        $pathString = "{$name}";
                        foreach (array_keys($pathMap) as $segment) {
                            $pathString .= " -> {$segment}";
                        }

                        $circularDependencyMap[$name] = $pathString;
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
     * @param array  $plugin
     * @param string $requiredVersion
     * @param array  &$errors
     * @return bool
     */
    private function checkDependency(array $plugin, string $requiredVersion, array &$errors): bool
    {
        if (Plugin::STATUS_OK !== $plugin['status']) {
            $errors[] = sprintf('dependency "%s" is not available', $plugin['id']);
            
            return false;
        }

        if (!$this->checkVersion($requiredVersion, $plugin['options']['version'])) {
            $errors[] = sprintf(
                'dependency "%s" (version "%s") is not compatible, version "%s" is required',
                $plugin['id'],
                $plugin['options']['version'],
                $requiredVersion
            );

            return false;
        }

        return true;
    }

    private function resolveAutoload(array $plugins, array &$autoload): void
    {
        foreach ($plugins as $plugin) {
            if (Plugin::STATUS_OK !== $plugin['status']) {
                continue;
            }

            $autoload['psr-4'][$plugin['options']['namespace'] . '\\'] = $plugin['dir'];

            foreach ($plugin['options']['autoload'] as $type => $entries) {
                $autoload[$type] += $entries;
            }
        }
    }

    private function handleComposerRepositories(array &$plugins, array &$boundFiles, RepositoryInjector $injector): void
    {
        foreach ($plugins as &$plugin) {
            if (!$plugin['options']['inject_composer']) {
                continue;
            }

            $composerJsonPath = $plugin['dir'] . '/composer.json';

            if (is_file($composerJsonPath)) {
                $repository = new Repository($composerJsonPath);

                if (!is_dir($repository->getVendorPath())) {
                    $plugin = $this->convertPluginToErrorState(
                        $plugin,
                        [sprintf('missing dependencies, please run "composer install" in %s', $repository->getDirectory())]
                    );
                    continue;
                }

                $this->ensureComposerRepositoryAccessControl($repository);

                if (!$injector->inject($repository, $errors)) {
                    $plugin = $this->convertPluginToErrorState($plugin, $errors);
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
     * @param array $plugins
     */
    private function resolveInstallationStatus(array &$plugins): void
    {
        foreach ($plugins as &$plugin) {
            if (Plugin::STATUS_HAS_ERRORS !== $plugin['status'] && $plugin['options']['installer']) {
                $installer = PluginInstaller::load(
                    $plugin['dir'],
                    $plugin['options']['namespace'],
                    $plugin['camel_id']
                );

                $isInstalled = $installer->isInstalled();

                if (!$isInstalled && Plugin::STATUS_OK === $plugin['status']) {
                    $plugin['status'] = Plugin::STATUS_NEEDS_INSTALLATION;
                }

                $plugin['installed'] = $isInstalled;
            }
        }
    }
}
