<?php

namespace Sunlight\Plugin;

use Kuria\Cache\NamespacedCache;
use Sunlight\Core;
use Sunlight\Util\Form;

class PluginManager
{
    /** @var bool */
    private $safeMode;
    /** @var array<string, Type\PluginType> */
    private $types;
    /** @var PluginRegistry */
    private $plugins;
    /** @var PluginRouter */
    private $router;
    /** @var NamespacedCache */
    private $cache;
    /** @var bool */
    private $initialized = false;

    function __construct(bool $safeMode)
    {
        $this->safeMode = $safeMode;
        $this->types = [
            'extend' => new Type\ExtendPluginType(),
            'template' => new Type\TemplatePluginType(),
            'language' => new Type\LanguagePluginType(),
        ];
        $this->plugins = new PluginRegistry();
        $this->router = new PluginRouter();
        $this->cache = Core::$cache->getNamespace(Core::$debug ? 'plugins_debug.' : 'plugins.');
    }

    /**
     * Initialize the manager
     */
    function initialize(): void
    {
        if ($this->initialized) {
            throw new \LogicException('Already initialized');
        }

        // load data
        $data = $this->cache->get('data');

        // validate data
        if ($data !== null && !$this->validateCachedData($data)) {
            $data = null;
            $this->clearCache(); // clear entire cache if data is stale
        }

        // if data could not be loaded from cache, use plugin loader
        if ($data === null) {
            $data = $this->loadPlugins();
        }

        // setup autoload
        foreach ($data['autoload']['psr-0'] as $prefix => $paths) {
            Core::$classLoader->add($prefix, $paths);
        }

        foreach ($data['autoload']['psr-4'] as $prefix => $paths) {
            Core::$classLoader->addPsr4($prefix, $paths);
        }

        if (!empty($data['autoload']['classmap'])) {
            Core::$classLoader->addClassMap($data['autoload']['classmap']);
        }

        foreach ($data['autoload']['files'] as $path) {
            require $path;
        }

        // initialize plugins
        foreach ($data['plugins'] as $plugin) {
            /** @var PluginData $plugin */
            if ($plugin->isOk()) {
                /** @var Plugin $pluginInstance */
                $pluginInstance = new $plugin->options['class']($plugin, $this);
                $baseClass = $this->types[$plugin->type]->getClass();

                if (!is_a($pluginInstance, $baseClass)) {
                    throw new \LogicException(sprintf(
                        'Plugin class "%s" of plugin type "%s" must extend "%s"',
                        get_class($pluginInstance),
                        $plugin->type,
                        $this->types[$plugin->type]->getClass()
                    ));
                }

                $this->plugins->map[$plugin->id] = $pluginInstance;
                $this->plugins->typeMap[$plugin->type][$plugin->name] = $pluginInstance;

                if ($plugin->options['class'] !== $baseClass) {
                    $this->plugins->classMap[$plugin->options['class']] = $pluginInstance;
                }

                if ($pluginInstance instanceof InitializableInterface) {
                    try {
                        $pluginInstance->initialize();
                    } catch (\Throwable $e) {
                        throw new \RuntimeException(
                            sprintf('An exception was thrown while initializing plugin %s (%s): %s', $plugin->name, $plugin->id, $e->getMessage()),
                            0,
                            $e
                        );
                    }
                }
            } else {
                $pluginInstance = new InactivePlugin($plugin, $this);

                $this->plugins->inactiveMap[$plugin->id] = $pluginInstance;
                $this->plugins->inactiveTypeMap[$plugin->type][$plugin->name] = $pluginInstance;
            }
        }

        // done
        $this->initialized = true;
    }

    /**
     * Clear plugin cache
     */
    function clearCache(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Create a cache namespaced to a specific plugin
     */
    function createCacheForPlugin(Plugin $plugin): NamespacedCache
    {
        return new NamespacedCache(
            $this->cache->getWrappedCache(),
            $this->cache->getPrefix() . $plugin->getId()
        );
    }

    /**
     * Get all plugin types
     *
     * @return array<string, Type\PluginType>
     */
    function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Get currently loaded plugins
     */
    function getPlugins(): PluginRegistry
    {
        return $this->plugins;
    }

    function getRouter(): PluginRouter
    {
        return $this->router;
    }

    /**
     * Get plugins that depend on the given plugin
     *
     * Inactive plugins are not considered.
     *
     * @return Plugin[]
     */
    function getDependants(Plugin $plugin): array
    {
        $id = $plugin->getId();
        $dependants = [];

        foreach ($this->plugins->map as $otherPlugin) {
            if (isset($otherPlugin->getOption('dependencies')[$id])) {
                $dependants[] = $otherPlugin;
            }
        }

        return $dependants;
    }

    /**
     * Get name => label pairs for plugins of given type
     *
     * @return array<string, string>
     */
    function choices(string $type): array
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
        }

        $choices = [];

        foreach ($this->plugins->getByType($type) as $name => $plugin) {
            $choices[$name] = $plugin->getOption('name');
        }

        return $choices;
    }

    /**
     * Get HTML select for plugins of given type
     *
     * @param string|null $active active plugin name
     * @param string|null $inputName input name (null = no <select> tag, only options)
     */
    function select(string $type, ?string $active = null, ?string $inputName = null): string
    {
        $output = '';

        if ($inputName) {
            $output .= '<select name=\"' . $inputName . "\">\n";
        }

        foreach ($this->choices($type) as $name => $label) {
            $output .=
                '<option value="' . _e($name) . '"' . Form::selectOption($active === $name) . '>'
                . _e($label)
                . "</option>\n";
        }

        if ($inputName) {
            $output .= "</select>\n";
        }

        return $output;
    }

    private function loadPlugins(): array
    {
        $pluginLoader = new PluginLoader($this->safeMode, $this->types);
        $result = $pluginLoader->load();
        $data = [
            'plugins' => $result['plugins'],
            'autoload' => $result['autoload'],
            'bound_files' => $this->mapBoundFiles($result['bound_files']),
            'system_hash' => $this->getSystemHash(),
        ];

        $this->cache->set('data', $data);

        return $data;
    }

    private function validateCachedData(array $data): bool
    {
        if ($data['system_hash'] !== $this->getSystemHash()) {
            return false;
        }

        foreach ($data['bound_files'] as $path => $mtime) {
            if (@filemtime($path) !== $mtime) {
                return false;
            }
        }

        return true;
    }

    private function mapBoundFiles(array $boundFiles): array
    {
        $map = [];

        foreach ($boundFiles as $boundFile) {
            $map[realpath($boundFile)] = filemtime($boundFile);
        }

        return $map;
    }

    private function getSystemHash(): string
    {
        return sha1(Core::VERSION . '$' . SL_ROOT);
    }
}
