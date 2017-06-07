<?php

namespace Sunlight\Plugin;

use Sunlight\Plugin\Action\PluginAction;

abstract class Plugin
{
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
    protected $configurationErrors;
    /** @var string */
    protected $dir;
    /** @var string */
    protected $webPath;
    /** @var array */
    protected $options;
    /** @var PluginManager */
    protected $manager;

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
        $this->configurationErrors = $data['configuration_errors'];
        $this->dir = $data['dir'];
        $this->webPath = $data['web_path'];
        $this->options = $data['options'];
        $this->manager = $manager;
    }

    /**
     * Get plugin type definition
     *
     * @return array
     */
    public static function getTypeDefinition()
    {
        return static::$typeDefinition;
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
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get status
     *
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
        return $this->hasInstaller() && false === $this->installed;
    }

    /**
     * See if the plugin can be uninstalled
     *
     * @return bool
     */
    public function canBeUninstalled()
    {
        return $this->hasInstaller() && true === $this->installed;
    }

    /**
     * See if the plugin can be removed
     *
     * @return bool
     */
    public function canBeRemoved()
    {
        return !$this->hasInstaller() || false === $this->installed;
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
     * Get errors
     *
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get configuration errors
     *
     * @return string[]
     */
    public function getConfigurationErrors()
    {
        return $this->configurationErrors;
    }

    /**
     * Get directory
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->dir;
    }

    /**
     * Get web path
     *
     * @param bool $absolute
     * @return string
     */
    public function getWebPath($absolute = false)
    {
        return _link($this->webPath, $absolute);
    }

    /**
     * Get option
     *
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
     * Get all options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get action instance
     *
     * @param string $name
     * @return PluginAction|null
     */
    public function getAction($name)
    {
        switch ($name) {
            case 'info':
                return new Action\InfoAction($this);
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
            default:
                return $this->getCustomAction($name);
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
        global $_lang;

        if (!_env_admin) {
            throw new \RuntimeException('Plugin actions require administration environment');
        }

        $actions = array();

        $actions['info'] = $_lang['admin.plugins.action.do.info'];
        $actions += $this->getCustomActionList();
        if ($this->canBeInstalled()) {
            $actions['install'] = $_lang['admin.plugins.action.do.install'];
        }
        if ($this->canBeUninstalled()) {
            $actions['uninstall'] = $_lang['admin.plugins.action.do.uninstall'];
        }
        if ($this->canBeDisabled()) {
            $actions['disable'] = $_lang['admin.plugins.action.do.disable'];
        }
        if ($this->isDisabled()) {
            $actions['enable'] = $_lang['admin.plugins.action.do.enable'];
        }
        if ($this->canBeRemoved()) {
            $actions['remove'] = $_lang['admin.plugins.action.do.remove'];
        }

        return $actions;
    }

    /**
     * Get custom action instance
     *
     * @param string $name
     * @return PluginAction|null
     */
    protected function getCustomAction($name)
    {
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
