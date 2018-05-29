<?php

namespace Sunlight\Plugin;

use Kuria\Cache\CacheInterface;
use Kuria\ClassLoader\ClassLoader;
use Sunlight\Core;

class PluginManager
{
    /** Plugin type - language */
    const LANGUAGE = 'language';
    /** Plugin type - template */
    const TEMPLATE = 'template';
    /** Plugin type - extend */
    const EXTEND = 'extend';

    /**
     * Plugin list
     *
     * @var Plugin[] class => instance
     */
    private $plugins;

    /**
     * Plugin map
     *
     * array(
     *      type => array(name1 => instance1, ...),
     *      ...
     * )
     *
     * @var Plugin[][]
     */
    private $pluginMap;

    /**
     * Array of inactive plugins
     *
     * array(
     *      type => array(name1 => instance1, ...),
     *      ...
     * )
     *
     * @var InactivePlugin[][]
     */
    private $inactivePlugins;

    /** @var array[] */
    private $types;

    /** @var CacheInterface */
    private $cache;

    /** @var bool */
    private $initialized = false;

    /**
     * @param CacheInterface $pluginCache
     */
    function __construct(CacheInterface $pluginCache)
    {
        $this->cache = $pluginCache;
        $this->types = static::getTypeDefinitions();
    }

    /**
     * @return array
     */
    static function getTypeDefinitions()
    {
        return array(
            static::LANGUAGE => LanguagePlugin::getTypeDefinition(),
            static::TEMPLATE => TemplatePlugin::getTypeDefinition(),
            static::EXTEND => ExtendPlugin::getTypeDefinition(),
        );
    }

    /**
     * Purge plugin cache
     *
     * @return bool
     */
    function purgeCache()
    {
        return $this->cache->clear();
    }

    /**
     * See if the given type is valid
     *
     * @param string $type
     * @return bool
     */
    function isValidType($type)
    {
        return isset($this->types[$type]);
    }

    /**
     * Get all valid types
     *
     * @return string[]
     */
    function getTypes()
    {
        return array_keys($this->types);
    }

    /**
     * Get definition of the given type
     *
     * @param string $type
     * @throws \InvalidArgumentException if the plugin type is not valid
     * @return array
     */
    function getTypeDefinition($type)
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
        }

        return $this->types[$type];
    }

    /**
     * See if the given plugin class is active
     *
     * @param string $class
     * @return bool
     */
    function hasInstance($class)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return isset($this->plugins[$class]);
    }

    /**
     * Get plugin instance
     *
     * @param string $class
     * @throws \OutOfBoundsException if the plugin does not exist
     * @return Plugin|ExtendPlugin|TemplatePlugin|LanguagePlugin
     */
    function getInstance($class)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (isset($this->plugins[$class])) {
            return $this->plugins[$class];
        } else {
            throw new \OutOfBoundsException(sprintf('Plugin instance of class "%s/%s" does not exist', $class));
        }
    }

    /**
     * Get all plugin instances
     *
     * @return Plugin[]|ExtendPlugin[]|TemplatePlugin[]|LanguagePlugin[]
     */
    function getInstances()
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->plugins;
    }

    /**
     * See if the given plugin exists and is active
     *
     * @param string $type
     * @param string $name
     * @return bool
     */
    function has($type, $name)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return isset($this->pluginMap[$type][$name]);
    }

    /**
     * See if the given plugin exists (either active or inactive)
     *
     * @param string $type
     * @param string $name
     * @return bool
     */
    function exists($type, $name)
    {
        return $this->has($type, $name) || $this->hasInactive($type, $name);
    }

    /**
     * Get single plugin
     *
     * @param string $type
     * @param string $name
     * @throws \OutOfBoundsException if the plugin does not exist
     * @return Plugin|ExtendPlugin|TemplatePlugin|LanguagePlugin
     */
    function get($type, $name)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (isset($this->pluginMap[$type][$name])) {
            return $this->pluginMap[$type][$name];
        } else {
            throw new \OutOfBoundsException(sprintf('Plugin "%s/%s" does not exist', $type, $name));
        }
    }

    /**
     * Get a template plugin
     *
     * @param string $name
     * @throws \OutOfBoundsException if the plugin does not exist
     * @return TemplatePlugin
     */
    function getTemplate($name)
    {
        /** @var TemplatePlugin $plugin */
        $plugin = $this->get(static::TEMPLATE, $name);

        return $plugin;
    }

    /**
     * Get an extend plugin
     *
     * @param string $name
     * @throws \OutOfBoundsException if the plugin does not exist
     * @return ExtendPlugin
     */
    function getExtend($name)
    {
        /** @var ExtendPlugin $plugin */
        $plugin = $this->get(static::EXTEND, $name);

        return $plugin;
    }

    /**
     * Get a language plugin
     *
     * @param string $name
     * @throws \OutOfBoundsException if the plugin does not exist
     * @return LanguagePlugin
     */
    function getLanguage($name)
    {
        /** @var LanguagePlugin $plugin */
        $plugin = $this->get(static::LANGUAGE, $name);

        return $plugin;
    }

    /**
     * Get all plugins, optionally for only single type
     *
     * @param string|null $type specific type or null (all)
     * @throws \InvalidArgumentException if the plugin type is not valid
     * @return Plugin[]|Plugin[][] name indexed (if type is specified) or type and name indexed array of Plugin instances
     */
    function all($type = null)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if ($type !== null) {
            if (!isset($this->types[$type])) {
                throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
            }

            return $this->pluginMap[$type];
        } else {
            return $this->pluginMap;
        }
    }

    /**
     * @return LanguagePlugin[]
     */
    function getAllLanguages()
    {
        /** @var LanguagePlugin[] $languages */
        $languages = $this->all(static::LANGUAGE);

        return $languages;
    }

    /**
     * @return TemplatePlugin[]
     */
    function getAllTemplates()
    {
        /** @var TemplatePlugin[] $templates */
        $templates = $this->all(static::TEMPLATE);

        return $templates;
    }

    /**
     * @return ExtendPlugin[]
     */
    function getAllExtends()
    {
        /** @var ExtendPlugin[] $extends */
        $extends = $this->all(static::EXTEND);

        return $extends;
    }

    /**
     * See if the given inactive plugin exists
     *
     * @param string $type
     * @param string $name
     * @return bool
     */
    function hasInactive($type, $name)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return isset($this->inactivePlugins[$type][$name]);
    }

    /**
     * Get single inactive plugin
     *
     * @param string $type
     * @param string $name
     * @throws \OutOfBoundsException if the plugin does not exist
     * @return InactivePlugin
     */
    function getInactive($type, $name)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (isset($this->inactivePlugins[$type][$name])) {
            return $this->inactivePlugins[$type][$name];
        } else {
            throw new \OutOfBoundsException(sprintf('Inactive plugin "%s/%s" does not exist', $type, $name));
        }
    }

    /**
     * Get all inactive plugins
     *
     * @param string|null $type specific type or null (all)
     * @throws \InvalidArgumentException if the plugin type is not valid
     * @return InactivePlugin[]|InactivePlugin[][] name indexed (if type is specified) or type and name indexed array of plugin arrays
     */
    function getAllInactive($type = null)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if ($type !== null) {
            if (!isset($this->types[$type])) {
                throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
            }

            return $this->inactivePlugins[$type];
        } else {
            return $this->inactivePlugins;
        }
    }

    /**
     * Find a plugin (active or inactive)
     *
     * @param string $type
     * @param string $name
     * @param bool   $exceptionOnFailure
     * @throws \InvalidArgumentException if the plugin type is not valid
     * @throws \OutOfBoundsException     if the plugin does not exist
     * @return Plugin|null
     */
    function find($type, $name, $exceptionOnFailure = true)
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
        }

        if (isset($this->pluginMap[$type][$name])) {
            $plugin = $this->pluginMap[$type][$name];
        } elseif (isset($this->inactivePlugins[$type][$name])) {
            $plugin = $this->inactivePlugins[$type][$name];
        } else {
            if ($exceptionOnFailure) {
                throw new \OutOfBoundsException(sprintf('Could not find plugin "%s/%s"', $type, $name));
            } else {
                $plugin = null;
            }
        }

        return $plugin;
    }

    /**
     * Get name => label pairs for given plugin type
     *
     * @param string $type
     * @throws \InvalidArgumentException if the plugin type is not valid
     * @return array
     */
    function choices($type)
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
        }

        $choices = array();
        foreach ($this->pluginMap[$type] as $name => $instance) {
            $choices[$name] = $instance->getOption('name');
        }

        return $choices;
    }

    /**
     * Get HTML select for given plugin type
     *
     * @param string      $pluginType
     * @param string|null $active     active plugin name
     * @param string|null $inputName  input name (null = no <select> tag, only options)
     * @throws \InvalidArgumentException if the plugin type is not valid
     * @return string
     */
    function select($pluginType, $active = null, $inputName = null)
    {
        if (!isset($this->types[$pluginType])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $pluginType));
        }

        $output = "";
        if ($inputName) {
            $output .= "<select name=\"{$inputName}\">\n";
        }

        foreach ($this->choices($pluginType) as $name => $label) {
            $output .=
                '<option value="' . _e($name) . '"' . ($active === $name ? ' selected' : '') . '>'
                . _e($label)
                . "</option>\n";
        }
        if ($inputName) {
            $output .= "</select>\n";
        }

        return $output;
    }

    /**
     * Initialize the manager if not done yet
     */
    private function initialize()
    {
        if ($this->initialized) {
            return;
        }

        // load data
        $data = $this->cache->get('plugins');

        // invalidate stale data
        if (
            $data !== false
            && (
                $data['system_version'] !== Core::VERSION   // core updated
                || $data['root'] !== realpath(_root)        // root moved (cache contains absolute paths)
            )
        ) {
            $data = false;
        }

        // if data could not be loaded from cache, use plugin loader
        if ($data === false) {
            $data = $this->loadPlugins();
        }

        // setup autoload
        Core::$classLoader->addPrefixes($data['autoload']['psr-0'], ClassLoader::PSR0);
        Core::$classLoader->addPrefixes($data['autoload']['psr-4']);
        Core::$classLoader->addClassMap($data['autoload']['classmap']);

        foreach ($data['autoload']['files'] as $path) {
            require $path;
        }

        // initialize plugins
        $this->pluginMap = array();
        $this->inactivePlugins = array();

        foreach ($data['plugins'] as $type => $plugins) {
            $this->pluginMap[$type] = array();
            $this->inactivePlugins[$type] = array();

            foreach ($plugins as $name => $plugin) {
                if (Plugin::STATUS_OK === $plugin['status']) {
                    $pluginInstance = new $plugin['options']['class']($plugin, $this);

                    if (!is_a($pluginInstance, $this->types[$type]['class'])) {
                        throw new \LogicException(sprintf('Plugin class "%s" of plugin type "%s" must extend "%s"', get_class($pluginInstance), $type, $this->types[$type]['class']));
                    }

                    $this->plugins[$plugin['options']['class']] = $pluginInstance;
                    $this->pluginMap[$type][$name] = $pluginInstance;
                } else {
                    $this->inactivePlugins[$type][$name] = new InactivePlugin(
                        $plugin,
                        $this
                    );
                }
            }
        }

        // set variables
        $this->initialized = true;
    }

    /**
     * @return array
     */
    private function loadPlugins()
    {
        $pluginLoader = new PluginLoader($this->types);
        $result = $pluginLoader->load();

        $data = $result + array(
            'system_version' => Core::VERSION,
            'root' => realpath(_root),
        );

        $this->cache->set('plugins', $data, 0, array('bound_files' => $result['bound_files']));

        return $data;
    }
}
