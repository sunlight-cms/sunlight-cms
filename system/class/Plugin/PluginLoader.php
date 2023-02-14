<?php

namespace Sunlight\Plugin;

use Composer\Semver\Semver;
use Sunlight\Composer\Repository;
use Sunlight\Composer\RepositoryInjector;
use Sunlight\Core;
use Sunlight\Plugin\Type\PluginType;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;
use Sunlight\Util\StringManipulator;

class PluginLoader
{
    private const PLUGIN_DIR_PATTERN = '{' . Plugin::ID_PATTERN . '$}AD';

    /** @var PluginType[] */
    private $types;

    /**
     * @param PluginType[] $types
     */
    function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * Load plugin data from the filesystem
     *
     * @return array{
     *     plugins: array<string, PluginData>,
     *     autoload: array{
     *          psr-0: array<string, string[]>,
     *          psr-4: array<string, string[]>,
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
        $composerInjector = new RepositoryInjector(new Repository(realpath(SL_ROOT . '/composer.json')));

        $plugins = $this->findPlugins($boundFiles);
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
    private function findPlugins(array &$boundFiles): array
    {
        $plugins = [];

        // load plugins from standard paths
        foreach ($this->types as $type) {
            $dir = SL_ROOT . $type->getDir();

            foreach (scandir($dir) as $item) {
                if (
                    preg_match(self::PLUGIN_DIR_PATTERN, $item) // skips dots and invalid names
                    && is_dir("{$dir}/{$item}")
                    && ($plugin = $this->loadPlugin($dir, $item, $type))
                ) {
                    $plugins[$plugin->id] = $plugin;
                    $boundFiles[] = $plugin->file;
                }
            }
        }

        return $plugins;
    }

    private function loadPlugin(string $dir, string $name, PluginType $type): ?PluginData
    {
        $file = realpath("{$dir}/{$name}/" . Plugin::FILE);

        if ($file === false) {
            return null;
        }

        $plugin = new PluginData(
            "{$type->getName()}/{$name}",
            $name,
            StringManipulator::toCamelCase($name),
            $type->getName(),
            $file,
            "{$type->getDir()}/{$name}"
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
                $this->validateEnvironment($plugin);
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

        // override status if the plugin is disabled
        if ($isDisabled) {
            $plugin->status = Plugin::STATUS_DISABLED;
        }

        return $plugin;
    }

    private function validateEnvironment(PluginData $plugin): void
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

        // debug mode
        if ($env['debug'] !== null && $env['debug'] !== Core::$debug) {
            $plugin->addError(
                $env['debug']
                    ? 'plugin is only active in debug mode'
                    : 'plugin is not active in debug mode'
            );
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
                        $plugin->status = Plugin::STATUS_HAS_ERRORS;
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
        if (!$plugin->options['inject_composer']) {
            return;
        }

        $composerJsonPath = $plugin->dir . '/composer.json';

        if (is_file($composerJsonPath)) {
            $repository = new Repository($composerJsonPath);

            if (!is_dir($repository->getVendorPath())) {
                $errors[] = sprintf('composer dependencies are missing, try running "composer install" in %s', $repository->getDirectory());
                return;
            }

            $this->ensureComposerRepositoryAccessControl($repository);

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

            $autoload['psr-4'][$plugin->options['namespace'] . '\\'] = $plugin->dir;

            foreach ($plugin->options['autoload'] as $type => $entries) {
                $autoload[$type] += $entries;
            }
        }
    }

    private function ensureComposerRepositoryAccessControl(Repository $repository): void
    {
        if (!is_file($repository->getVendorPath() . '/.htaccess')) {
            Filesystem::denyAccessToDirectory($repository->getVendorPath());
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
