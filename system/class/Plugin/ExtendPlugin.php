<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;

class ExtendPlugin extends Plugin
{
    protected static $typeDefinition = array(
        'dir' => 'plugins/extend',
        'class' => __CLASS__,
        'default_base_namespace' => 'SunlightExtend',
        'options' => array(
            'events' => array('type' => 'array', 'required' => false, 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizeEvents')),
            'events.web' => array('type' => 'array', 'required' => false, 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizeEvents')),
            'events.admin' => array('type' => 'array', 'required' => false, 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizeEvents')),
            'scripts' => array('type' => 'array', 'required' => false, 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray')),
            'scripts.web' => array('type' => 'array', 'required' => false, 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray')),
            'scripts.admin' => array('type' => 'array', 'required' => false, 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray')),
            'langs' => array('type' => 'array', 'required' => false, 'default' => array(), 'normalizer' => array('Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray')),
        ),
    );

    /**
     * Initialize the plugin
     */
    public function initialize()
    {
        // register events
        foreach ($this->options['events'] as $subscriber) {
            Extend::reg(
                $subscriber['event'],
                $subscriber['use_this']
                    ? array($this, $subscriber['callback'])
                    : $subscriber['callback'],
                $subscriber['priority']
            );
        }
        if (_env === Core::ENV_WEB || _env === Core::ENV_ADMIN) {
            foreach ($this->options['events.' . _env] as $subscriber) {
                Extend::reg(
                    $subscriber['event'],
                    $subscriber['use_this']
                        ? array($this, $subscriber['callback'])
                        : $subscriber['callback'],
                    $subscriber['priority']
                );
            }
        }

        // register language packs
        foreach ($this->options['langs'] as $key => $dir) {
            Core::$lang->registerSubDictionary($key, new LocalizationDirectory($dir));
        }

        // load scripts
        foreach ($this->options['scripts'] as $script) {
            $this->loadScript($script);
        }
        if (_env === Core::ENV_WEB || _env === Core::ENV_ADMIN) {
            foreach ($this->options['scripts.' . _env] as $script) {
                $this->loadScript($script);
            }
        }
    }

    /**
     * Load a script
     *
     * @param string $script
     */
    protected function loadScript($script)
    {
        include $script;
    }
}
