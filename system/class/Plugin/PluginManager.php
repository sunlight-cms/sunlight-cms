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
     * Array of plugins
     *
     * array(
     *      type => array(name1 => instance1, ...),
     *      ...
     * )
     *
     * @var Plugin[][]
     */
    private $plugins;
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
    public function __construct(CacheInterface $pluginCache)
    {
        $this->cache = $pluginCache;
        
        $this->types = static::getTypeDefinitions();
    }

    /**
     * @return array
     */
    public static function getTypeDefinitions()
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
    public function purgeCache()
    {
        return $this->cache->clear();
    }

    /**
     * See if the given type is valid
     *
     * @param string $type
     * @return bool
     */
    public function isValidType($type)
    {
        return isset($this->types[$type]);
    }

    /**
     * Get all valid types
     *
     * @return string[]
     */
    public function getTypes()
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
    public function getTypeDefinition($type)
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
        }

        return $this->types[$type];
    }

    /**
     * See if the given plugin exists and is active
     *
     * @param string $type
     * @param string $name
     * @return bool
     */
    public function has($type, $name)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return isset($this->plugins[$type][$name]);
    }

    /**
     * See if the given plugin exists (either active or inactive)
     *
     * @param string $type
     * @param string $name
     * @return bool
     */
    public function exists($type, $name)
    {
        return $this->has($type, $name) || $this->hasInactive($type, $name);
    }

    /**
     * Get single plugin
     *
     * @param string $type
     * @param string $name
     * @throws \OutOfBoundsException if the plugin does not exist
     * @return Plugin
     */
    public function get($type, $name)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (isset($this->plugins[$type][$name])) {
            return $this->plugins[$type][$name];
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
    public function getTemplate($name)
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
    public function getExtend($name)
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
    public function getLanguage($name)
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
    public function all($type = null)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if ($type !== null) {
            if (!isset($this->types[$type])) {
                throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
            }

            return $this->plugins[$type];
        } else {
            return $this->plugins;
        }
    }

    /**
     * @return LanguagePlugin[]
     */
    public function getAllLanguages()
    {
        /** @var LanguagePlugin[] $languages */
        $languages = $this->all(static::LANGUAGE);

        return $languages;
    }

    /**
     * @return TemplatePlugin[]
     */
    public function getAllTemplates()
    {
        /** @var TemplatePlugin[] $templates */
        $templates = $this->all(static::TEMPLATE);

        return $templates;
    }

    /**
     * @return ExtendPlugin[]
     */
    public function getAllExtends()
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
    public function hasInactive($type, $name)
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
    public function getInactive($type, $name)
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
    public function allInactive($type = null)
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
    public function find($type, $name, $exceptionOnFailure = true)
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
        }

        if (isset($this->plugins[$type][$name])) {
            $plugin = $this->plugins[$type][$name];
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
    public function choices($type)
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Invalid plugin type "%s"', $type));
        }

        $choices = array();
        foreach ($this->plugins[$type] as $name => $instance) {
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
    public function select($pluginType, $active = null, $inputName = null)
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
        $data = $this->cache->get('plugin_data');

        // invalidate stale data
        if ($data !== false && $data['system_version'] !== Core::VERSION) {
            $data = false;
        }

        // if data could not be loaded from cache, use plugin loader
        if ($data === false) {
            $data = $this->loadPlugins();
        }

        // initialize plugins
        $this->plugins = array();
        $this->inactivePlugins = array();

        foreach ($data['plugins'] as $type => $plugins) {
            $this->plugins[$type] = array();
            $this->inactivePlugins[$type] = array();

            foreach ($plugins as $name => $plugin) {
                if (Plugin::STATUS_OK === $plugin['status']) {
                    // setup autoloading
                    $this->setupAutoload($plugin);

                    // create instance
                    $pluginInstance = new $plugin['options']['class']($plugin, $this);

                    if (!is_a($pluginInstance, $this->types[$type]['class'])) {
                        throw new \LogicException(sprintf('Plugin class "%s" of plugin type "%s" must extend "%s"', get_class($pluginInstance), $type, $this->types[$type]['class']));
                    }

                    $this->plugins[$type][$name] = $pluginInstance;
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
        $loadedPlugins = $pluginLoader->load();

        $pluginFiles = array();
        foreach ($loadedPlugins as $type => $plugins) {
            foreach ($plugins as $plugin) {
                $pluginFiles[] = $plugin['file'];
            }
        }

        $data = array(
            'plugins' => $loadedPlugins,
            'system_version' => Core::VERSION,
        );

        $this->cache->set('plugin_data', $data, 0, array('bound_files' => $pluginFiles));

        return $data;
    }

    /**
     * Setup autoloading
     *
     * @param array $plugin
     */
    private function setupAutoload(array $plugin)
    {
        // plugin namespace
        Core::$classLoader->addPrefix($plugin['options']['namespace'] . '\\', $plugin['dir']);

        // custom
        foreach ($plugin['options']['autoload'] as $type => $entries) {
            switch ($type) {
                case Plugin::AUTOLOAD_PSR0:
                    Core::$classLoader->addPrefixes($entries, ClassLoader::PSR0);
                    break;

                case Plugin::AUTOLOAD_PSR4:
                    Core::$classLoader->addPrefixes($entries, ClassLoader::PSR4);
                    break;

                case Plugin::AUTOLOAD_CLASSMAP:
                    Core::$classLoader->addClassMap($entries);
                    break;

                default:
                    throw new \LogicException('Invalid autoload type');
            }
        }
    }
}
