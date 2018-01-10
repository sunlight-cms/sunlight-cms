<?php

namespace Sunlight\Plugin;

use Composer\Semver\Semver;
use Sunlight\Core;
use Sunlight\Option\OptionSet;
use Sunlight\Util\Json;

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
    /** @var array[]|null */
    private $composerPackages;

    /**
     * @param array $types
     */
    public function __construct(array $types)
    {
        $this->types = $types;
        $this->pluginIdPattern = '/^' . Plugin::ID_PATTERN . '$/';

        $this->commonOptionSet = new OptionSet(Plugin::$commonOptions);
        $this->commonOptionSet->setIgnoreExtraIndexes(true);

        $this->typeOptionSets = array();
        foreach ($types as $type => $typeDefinition) {
            $this->typeOptionSets[$type] = new OptionSet($typeDefinition['options']);
            $this->typeOptionSets[$type]->addKnownIndexes($this->commonOptionSet->getIndexes());
        }
    }

    /**
     * Load plugin data from the filesystem
     *
     * @param bool $checkDebugMode
     * @param bool $resolveInstallationStatus
     * @return array
     */
    public function load($checkDebugMode = true, $resolveInstallationStatus = true)
    {
        $plugins = array();

        $this->loadLocalPlugins($plugins, $checkDebugMode);
        $this->loadComposerPlugins($plugins, $checkDebugMode);

        // resolve dependencies
        foreach ($this->types as $typeName => $type) {
            $plugins[$typeName] = $this->resolveDependencies($plugins[$typeName]);
        }

        // resolve installation status
        if ($resolveInstallationStatus) {
            foreach ($this->types as $typeName => $type) {
                $this->resolveInstallationStatus($plugins[$typeName]);
            }
        }

        return $plugins;
    }

    /**
     * @param array $plugins
     * @param bool  $checkDebugMode
     */
    private function loadLocalPlugins(array &$plugins, $checkDebugMode)
    {
        // load plugins from standard paths
        foreach ($this->types as $typeName => $type) {
            $plugins[$typeName] = array();

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
                    $plugins[$typeName][$item] = $this->loadPlugin(
                        $this->createPluginData(
                            $item,
                            $pluginFile,
                            $type['dir'] . '/' . $item,
                            $typeName,
                            Plugin::SOURCE_LOCAL
                        ),
                        $checkDebugMode
                    );
                }
            }
        }
    }

    /**
     * @param array $plugins
     * @param bool  $checkDebugMode
     */
    private function loadComposerPlugins(array &$plugins, $checkDebugMode)
    {
        foreach ($this->getComposerPackages() as $package) {
            if (!isset($package['extra']['sunlight-cms-8-plugins']) || !is_array($package['extra']['sunlight-cms-8-plugins'])) {
                continue;
            }

            foreach ($package['extra']['sunlight-cms-8-plugins'] as $pluginId => $pluginParams) {
                $pluginFile = sprintf('%svendor/%s/%s/%s', _root, $package['name'], $pluginParams['path'], Plugin::FILE);

                if (!is_file($pluginFile)) {
                    continue;
                }

                $plugin = $this->createPluginData(
                    $pluginId,
                    $pluginFile,
                    "vendor/{$package['name']}/{$pluginParams['path']}",
                    $pluginParams['type'],
                    Plugin::SOURCE_COMPOSER,
                    array('version' => $package['version'])
                );

                if (!preg_match($this->pluginIdPattern, $pluginId)) {
                    $plugin['id'] = $this->generateTemporaryComposerPluginId($pluginFile);

                    $plugin['errors'][] = sprintf(
                        'plugin ID "%s" specified by Composer package "%s" does not match "%s"',
                        $pluginId,
                        $package['name'],
                        $this->pluginIdPattern
                    );
                } elseif (isset($plugins[$pluginParams['type']][$pluginId])) {
                    $plugin['id'] = $this->generateTemporaryComposerPluginId($pluginFile);

                    $plugin['errors'][] = sprintf(
                        'cannot load plugin "%s/%s" from Composer package "%s" because such plugin already exists at "%s"',
                        $pluginParams['type'],
                        $pluginId,
                        $package['name'],
                        $plugins[$pluginParams['type']][$pluginId]['file']
                    );
                }

                $plugins[$pluginParams['type']][$plugin['id']] = $this->loadPlugin($plugin, $checkDebugMode);
            }
        }
    }

    /**
     * @param string $pluginFile
     * @return string
     */
    private function generateTemporaryComposerPluginId($pluginFile)
    {
        return sprintf('composer__invalid_plugin_%x', crc32($pluginFile));
    }

    /**
     * @param array $plugin
     * @param bool  $checkDebugMode
     * @return array
     */
    public function loadPlugin(array $plugin, $checkDebugMode = true)
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

            $this->validateOptions($options, $plugin['definition_errors'], $checkDebugMode, $plugin['errors']);
        }

        // handle result
        if (empty($plugin['errors']) && empty($plugin['definition_errors'])) {
            // ok
            $plugin['status'] = Plugin::STATUS_OK;
            $plugin['options'] = $options;
        } else {
            // there are errors
            $plugin['status'] = Plugin::STATUS_HAS_ERRORS;
            if ($options !== null && empty($plugin['definition_errors'])) {
                $plugin['options'] = $options;
            } else {
                $options = array(
                    'id' => $plugin['id'],
                    'name' => $plugin['id'],
                    'version' => '0.0.0',
                    'api' => '0.0.0',
                );
                $this->commonOptionSet->process($options, $context);
                $plugin['options'] = $options;
            }
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
     * @param array $configurationErrors
     * @param bool  $checkDebugMode
     * @param array &$errors
     */
    private function validateOptions(array $options, array $configurationErrors, $checkDebugMode, array &$errors)
    {
        // api version
        if (!isset($configurationErrors['api']) && !$this->checkVersion($options['api'], Core::VERSION)) {
            $errors[] = sprintf('API version "%s" is not compatible with system version "%s"', $options['api'], Core::VERSION);
        }

        // PHP version
        if (!isset($configurationErrors['php']) && $options['php'] !== null && !version_compare($options['php'], PHP_VERSION, '<=')) {
            $errors[] = sprintf('PHP version "%s" or newer is required', $options['php']);
        }

        // extensions
        if (!isset($configurationErrors['extensions'])) {
            foreach ($options['extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    $errors[] = sprintf('PHP extension "%s" is required', $extension);
                }
            }
        }

        // debug mode
        if ($checkDebugMode && !isset($configurationErrors['debug']) && $options['debug'] !== null && $options['debug'] !== _debug) {
            $errors[] = $options['debug']
                ? 'debug mode is required'
                : 'production mode is required';
        }

        // composer dependencies
        $composerPackages = $this->getComposerPackages();

        foreach ($options['requires.composer'] as $requiredPackage => $requiredPackageVersion) {
            if (!isset($composerPackages[$requiredPackage])) {
                $errors[] = sprintf('required Composer package "%s" is not installed', $requiredPackage);
            } elseif (!$this->checkVersion($requiredPackageVersion, $composerPackages[$requiredPackage]['version'])) {
                $errors[] = sprintf(
                    'required Composer package "%s" is installed in version "%s", but version "%s" is required',
                    $requiredPackage,
                    $composerPackages[$requiredPackage]['version'],
                    $requiredPackageVersion
                );
            }
        }
    }

    /**
     * @param array $plugin
     * @param array $type
     * @return string
     */
    private function resolvePluginClass(array $plugin, array $type)
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
    public function checkVersion($requiredVersion, $actualVersion)
    {
        return Semver::satisfies($actualVersion, $requiredVersion);
    }

    /**
     * @param string $id
     * @param string $file
     * @param string|null $webPath
     * @param string $typeName
     * @param string $source
     * @param array $defaultOptions
     * @return array
     */
    private function createPluginData($id, $file, $webPath, $typeName, $source, array $defaultOptions = array())
    {
        $file = realpath($file);

        return array(
            'id' => $id,
            'camel_id' => _camelCase($id),
            'type' => $typeName,
            'source' => $source,
            'status' => null,
            'installed' => null,
            'dir' => dirname($file),
            'file' => $file,
            'web_path' => $webPath,
            'errors' => array(),
            'definition_errors' => array(),
            'options' => $defaultOptions + array('name' => $id),
        );
    }

    /**
     * @param array $plugin
     * @param array $type
     * @return array
     */
    private function createPluginOptionContext(array &$plugin, array $type)
    {
        return array(
            'plugin' => &$plugin,
            'type' => $type,
        );
    }

    /**
     * Resolve plugin dependencies
     *
     * @param array $plugins
     * @throws \RuntimeException if the dependencies cannot be resolved
     * @return array
     */
    private function resolveDependencies(array $plugins)
    {
        $sorted = array();
        $circularDependencyMap = $this->findCircularDependencies($plugins);

        while (!empty($plugins)) {
            $numAdded = 0;
            foreach ($plugins as $name => $plugin) {
                $canBeAdded = true;
                $errors = array();

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
                    $sorted[$name] = array(
                        'status' => Plugin::STATUS_HAS_ERRORS,
                        'errors' => $errors
                    ) + $plugin;
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
    private function findCircularDependencies(array $plugins)
    {
        $circularDependencyMap = array();

        $checkQueue = array();
        foreach ($plugins as $name => $plugin) {
            if (Plugin::STATUS_OK === $plugin['status']) {
                foreach (array_keys($plugin['options']['requires']) as $dependency) {
                    $checkQueue[] = array($dependency, array($name => true, $dependency => true));
                }
            }
        }

        while (!empty($checkQueue)) {
            list($name, $pathMap) = array_pop($checkQueue);

            if (isset($plugins[$name])) {
                foreach (array_keys($plugins[$name]['options']['requires']) as $dependency) {
                    if (isset($pathMap[$dependency])) {
                        $pathString = "{$name}";
                        foreach (array_keys($pathMap) as $segment) {
                            $pathString .= " -> {$segment}";
                        }

                        $circularDependencyMap[$name] = $pathString;
                    } else {
                        $checkQueue[] = array($dependency, $pathMap + array($dependency => true));
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
    private function checkDependency(array $plugin, $requiredVersion, array &$errors)
    {
        if (Plugin::STATUS_OK !== $plugin['status']) {
            $errors[] = sprintf('dependency "%s" is not available', $plugin['id']);
            
            return false;
        } elseif (!$this->checkVersion($requiredVersion, $plugin['options']['version'])) {
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

    /**
     * Resolve installation status
     *
     * @param array &$plugins
     */
    private function resolveInstallationStatus(array &$plugins)
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

    /**
     * @return array[]
     */
    private function getComposerPackages()
    {
        if ($this->composerPackages !== null) {
            return $this->composerPackages;
        }

        $this->composerPackages = array();

        if (is_file($installedJson = _root . '/vendor/composer/installed.json')) {
            foreach (Json::decode(file_get_contents($installedJson)) as $package) {
                $this->composerPackages[$package['name']] = $package;
            }
        }

        return $this->composerPackages;
    }
}
