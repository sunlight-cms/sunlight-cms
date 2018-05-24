<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Plugin\Action\PluginAction;
use Sunlight\Util\ConfigurationFile;

abstract class Plugin
{
    /** ID pattern */
    const ID_PATTERN = '[a-zA-Z][a-zA-Z0-9_.\-]*';
    /** Name of the plugin definition file */
    const FILE = 'plugin.json';
    /** Name of the plugin deactivating file */
    const DEACTIVATING_FILE = 'DISABLED';

    /** Plugin status - OK */
    const STATUS_OK = 0;
    /** Plugin status - has error messages */
    const STATUS_HAS_ERRORS = 1;
    /** Plugin status - not installed */
    const STATUS_NEEDS_INSTALLATION = 2;
    /** Plugin status - disabled */
    const STATUS_DISABLED = 3;

    /** Autoload type - PSR0 */
    const AUTOLOAD_PSR0 = 0;
    /** Autoload type - PSR4 */
    const AUTOLOAD_PSR4 = 1;
    /** Autload type - class map */
    const AUTOLOAD_CLASSMAP = 2;

    /** @var array */
    public static $commonOptions = array(
        'name' => array('type' => 'string', 'required' => true),
        'description' => array('type' => 'string'),
        'author' => array('type' => 'string'),
        'url' => array('type' => 'string'),
        'version' => array('type' => 'string', 'required' => true),
        'api' => array('type' => 'string', 'required' => true),
        'php' => array('type' => 'string'),
        'extensions' => array('type' => 'array', 'default' => array()),
        'requires' => array('type' => 'array', 'default' => array()),
        'installer' => array('type' => 'boolean', 'nullable' => true, 'default' => false),
        'autoload' => array('type' => 'array', 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizeAutoload')),
        'debug' => array('type' => 'boolean', 'nullable' => true),
        'class' => array('type' => 'string'),
        'namespace' => array('type' => 'string', 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizeNamespace')),
        'inject_composer' => array('type' => 'boolean', 'default' => true),
    );

    /** @var array */
    protected static $typeDefinition = array();

    /** @var string */
    protected $type;
    /** @var string */
    protected $id;
    /** @var string */
    protected $camelId;
    /** @var int */
    protected $status;
    /** @var bool|null */
    protected $installed;
    /** @var string[] */
    protected $errors;
    /** @var string[] */
    protected $definitionErrors;
    /** @var string */
    protected $dir;
    /** @var string */
    protected $file;
    /** @var string */
    protected $webPath;
    /** @var array */
    protected $options;
    /** @var PluginManager */
    protected $manager;
    /** @var ConfigurationFile|null */
    private $config;

    /**
     * @param array         $data
     * @param PluginManager $manager
     */
    public function __construct(array $data, PluginManager $manager)
    {
        $this->type = $data['type'];
        $this->id = $data['id'];
        $this->camelId = $data['camel_id'];
        $this->status = $data['status'];
        $this->installed = $data['installed'];
        $this->errors = $data['errors'];
        $this->definitionErrors = $data['definition_errors'];
        $this->dir = $data['dir'];
        $this->file = $data['file'];
        $this->webPath = $data['web_path'];
        $this->options = $data['options'];
        $this->manager = $manager;
    }

    /**
     * @return array
     */
    public static function getTypeDefinition()
    {
        return static::$typeDefinition;
    }

    /**
     * See if this plugin is currently active
     *
     * @return bool
     */
    public static function isActive()
    {
        return Core::$pluginManager->hasInstance(get_called_class());
    }

    /**
     * Get plugin instance
     *
     * @throws \OutOfBoundsException if the plugin is not currently active
     * @return static
     */
    public static function getInstance()
    {
        return Core::$pluginManager->getInstance(get_called_class());
    }

    /**
     * Get plugin identifier
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get camel cased plugin identifier
     *
     * @return string
     */
    public function getCamelId()
    {
        return $this->camelId;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * See if the plugin is disabled
     *
     * @return bool
     */
    public function isDisabled()
    {
        return static::STATUS_DISABLED === $this->status;
    }

    /**
     * See if the plugin can be disabled
     *
     * @return bool
     */
    public function canBeDisabled()
    {
        return !$this->isDisabled();
    }

    /**
     * See if the plugin has been installed
     *
     * @return bool|null null if the plugin has no installer
     */
    public function isInstalled()
    {
        return $this->installed;
    }

    /**
     * See if the plugin has an installer
     *
     * @return bool
     */
    public function hasInstaller()
    {
        return $this->options['installer'];
    }

    /**
     * Get installer for this plugin
     *
     * @throws \LogicException if the plugin has no installer
     * @return PluginInstaller
     */
    public function getInstaller()
    {
        if (!$this->hasInstaller()) {
            throw new \LogicException('Plugin has no installer');
        }

        return PluginInstaller::load($this->dir, $this->options['namespace'], $this->camelId);
    }

    /**
     * See if the plugin needs installation be activated
     *
     * @return bool
     */
    public function needsInstallation()
    {
        return static::STATUS_NEEDS_INSTALLATION === $this->status;
    }

    /**
     * See if the plugin can be installed
     *
     * @return bool
     */
    public function canBeInstalled()
    {
        return $this->hasInstaller() && $this->installed === false;
    }

    /**
     * See if the plugin can be uninstalled
     *
     * @return bool
     */
    public function canBeUninstalled()
    {
        return $this->hasInstaller() && $this->installed === true;
    }

    /**
     * See if the plugin can be removed
     *
     * @return bool
     */
    public function canBeRemoved()
    {
        return !$this->hasInstaller() || $this->installed === false;
    }

    /**
     * See if the plugin has errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        return static::STATUS_HAS_ERRORS === $this->status;
    }

    /**
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return string[]
     */
    public function getDefinitionErrors()
    {
        return $this->definitionErrors;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->dir;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param bool $absolute
     * @return string
     */
    public function getWebPath($absolute = false)
    {
        return _link($this->webPath, $absolute);
    }

    /**
     * @param string $name
     * @throws \OutOfBoundsException if the option does not exist
     * @return mixed
     */
    public function getOption($name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \OutOfBoundsException(sprintf('Option "%s" does not exist', $name));
        }

        return $this->options[$name];
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get plugin configuration
     *
     * @return ConfigurationFile
     */
    public function getConfig()
    {
        if ($this->config === null) {
            $defaults = $this->getConfigDefaults();

            if (empty($defaults)) {
                throw new \LogicException('To use the configuration file, defaults must be specified by overriding the getConfigDefaults() method');
            }

            $this->config = new ConfigurationFile($this->getConfigPath(), $defaults);
        }

        return $this->config;
    }

    /**
     * @param string $key
     * @return string
     */
    public function getConfigLabel($key)
    {
        return $key;
    }

    /**
     * @return array
     */
    protected function getConfigDefaults()
    {
        return array();
    }

    /**
     * @return string
     */
    protected function getConfigPath()
    {
        return $this->dir . '/config.php';
    }

    /**
     * @param string $name
     * @return PluginAction|null
     */
    public function getAction($name)
    {
        switch ($name) {
            case 'info':
                return new Action\InfoAction($this);
            case 'config':
                return new Action\ConfigAction($this);
            case 'install':
                return new Action\InstallAction($this);
            case 'uninstall':
                return new Action\UninstallAction($this);
            case 'disable':
                return new Action\DisableAction($this);
            case 'enable':
                return new Action\EnableAction($this);
            case 'remove':
                return new Action\RemoveAction($this);
        }
    }

    /**
     * Get list of currently available actions
     *
     * @throws \RuntimeException if run outside of administration environment
     * @return string[] name => label
     */
    public function getActionList()
    {
        if (_env !== Core::ENV_ADMIN) {
            throw new \RuntimeException('Plugin actions require administration environment');
        }

        $actions = array();

        $actions['info'] = _lang('admin.plugins.action.do.info');
        $actions += $this->getCustomActionList();
        if (sizeof($this->getConfigDefaults())) {
            $actions['config'] = _lang('admin.plugins.action.do.config');
        }
        if ($this->canBeInstalled()) {
            $actions['install'] = _lang('admin.plugins.action.do.install');
        }
        if ($this->canBeUninstalled()) {
            $actions['uninstall'] = _lang('admin.plugins.action.do.uninstall');
        }
        if ($this->canBeDisabled()) {
            $actions['disable'] = _lang('admin.plugins.action.do.disable');
        }
        if ($this->isDisabled()) {
            $actions['enable'] = _lang('admin.plugins.action.do.enable');
        }
        if ($this->canBeRemoved()) {
            $actions['remove'] = _lang('admin.plugins.action.do.remove');
        }

        return $actions;
    }

    /**
     * Get list of custom actions
     *
     * @return string[] name => label
     */
    protected function getCustomActionList()
    {
        return array();
    }
}
