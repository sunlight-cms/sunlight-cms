<?php

namespace Sunlight\Plugin;

use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;

class ComposerBridge
{
    public static function linkPluginDependencies()
    {
        \Sunlight\Composer\ComposerBridge::initMinimalCore();

        // prepare plugin package cache
        $packageCachePath = _root . 'plugins/.composer';
        
        if (is_dir($packageCachePath)) {
            Filesystem::purgeDirectory($packageCachePath, array('keep_dir' => true));
        }

        // create packages for plugins with specified composer dependecies
        $rootRequirements = array();
        $pluginLoader = new PluginLoader(PluginManager::getTypeDefinitions());

        foreach ($pluginLoader->load(false, false) as $pluginType => $plugins) {
            foreach ($plugins as $pluginId => $plugin) {
                if ($plugin['status'] !== Plugin::STATUS_OK || $plugin['source'] !== Plugin::SOURCE_LOCAL) {
                    continue;
                }

                if (empty($plugin['options']['requires.composer'])) {
                    continue;
                }

                $pluginRequirements = $plugin['options']['requires.composer'];
                $pluginComposerName = sprintf('sunlight-cms/plugin-%s-%s', $pluginType, $pluginId);
                $pluginVersion = sprintf(
                    '%s-p%s',
                    $plugin['options']['version'],
                    // dynamic patch number makes composer notice requirement changes
                    crc32(json_encode($pluginRequirements))
                );

                static::createDummyPackage(
                    sprintf('%s/%s.%s', $packageCachePath, $pluginType, $pluginId),
                    $pluginComposerName,
                    $pluginVersion,
                    $pluginRequirements
                );

                $rootRequirements[$pluginComposerName] = $pluginVersion;
            }
        }

        // create root package that requires above packages
        static::createDummyPackage(
            $packageCachePath . '/all',
            'sunlight-cms/plugin-root',
            'dev-master',
            $rootRequirements
        );

        Filesystem::denyAccessToDirectory($packageCachePath);

        // done
        echo 'Found ', sizeof($rootRequirements), " local plugins with Composer dependencies\n";
    }

    /**
     * @param string $path
     * @param string $name
     * @param string $version
     * @param array $requirements
     */
    protected static function createDummyPackage($path, $name, $version, array $requirements)
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }

        file_put_contents(
            $path . '/composer.json',
            Json::encode(array(
                'name' => $name,
                'description' => 'THIS FILE IS GENERATED AUTOMATICALLY, DO NOT EDIT!',
                'version' => $version,
                'require' => $requirements,
            ))
        );
    }
}
