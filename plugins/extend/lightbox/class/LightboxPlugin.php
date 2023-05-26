<?php

namespace SunlightExtend\Lightbox;

use Sunlight\Core;
use Sunlight\Plugin\Action\PluginAction;
use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Settings;
use Sunlight\Template;
use Sunlight\Util\Json;

class LightboxPlugin extends ExtendPlugin
{
    function onLightbox(array $args): void
    {
        $this->enableEventGroup('lightbox');

        $args['output'] .= "data-lightbox='" . $args['group'] . "'";
    }

    function onHead(array $args): void
    {
        $dark = $this->getConfig()['dark_mode'];

        if ($dark === null) {
            if (Core::$env === Core::ENV_ADMIN) {
                $dark = Settings::get('adminscheme_dark');
            } else {
                $dark = Template::getCurrent()->getOption('dark');
            }
        }

        $args['css']['lightbox'] = $this->getAssetPath('public/css/lightbox' . ($dark ? '-dark' : '') . '.css');
    }

    function onEnd(array $args): void
    {
        $options =  $this->getConfig()['options'] + ['albumLabel' => _lang('lightbox.album_label')];

        $args['output'] .= '<script src="' . _e($this->getAssetPath('public/js/lightbox.js')) . '"></script>' . "\n";
        $args['output'] .= '<script>lightbox.option(' . Json::encodeForInlineJs($options) . ');</script>' . "\n";
    }

    function getAction(string $name): ?PluginAction
    {
        if ($name === 'config') {
            return new ConfigAction($this);
        }

        return parent::getAction($name);
    }

    protected function getConfigDefaults(): array
    {
        return [
            'dark_mode' => null,
            'options' => [
                'fadeDuration' => 300,
                'resizeDuration' => 300,
            ],
        ];
    }
}
