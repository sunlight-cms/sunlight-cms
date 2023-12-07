<?php

namespace SunlightExtend\Lightbox;

use Sunlight\Plugin\Action\ConfigAction as BaseConfigAction;
use Sunlight\Util\ConfigurationFile;
use Sunlight\Util\Form;
use Sunlight\Util\Json;

class ConfigAction extends BaseConfigAction
{
    protected function getFields(): array
    {
        $config = $this->plugin->getConfig();

        return [
            'dark_mode' => [
                'label' => _lang('lightbox.cfg.dark_mode'),
                'input' => Form::select(
                    'config[dark_mode]',
                    [
                        '' => _lang('lightbox.cfg.dark_mode.auto'),
                        1 => _lang('global.yes'),
                        0 => _lang('global.no'),
                    ],
                    $config['dark_mode'] === null ? '' : (int) $config['dark_mode']
                ),
            ],
            'options' => [
                'label' => _lang('lightbox.cfg.options'),
                'input' => Form::textarea('config[options]', Json::encode($config['options'], Json::PRETTY), ['class' => 'areasmall']) . '<br>'
                    . '<p>' . _lang('lightbox.cfg.options.docs') . '</p>',
            ],
        ];
    }

    protected function mapSubmittedValue(ConfigurationFile $config, string $key, array $field, $value): ?string
    {
        switch ($key) {
            case 'dark_mode':
                $config[$key] = ($value === '' ? null : (bool) $value);
                return null;
            case 'options':
                try {
                    $config[$key] = Json::decode($value);
                } catch (\InvalidArgumentException $e) {
                    return $e->getMessage();
                }

                return null;
        }

        return parent::mapSubmittedValue($config, $key, $field, $value);
    }
}
