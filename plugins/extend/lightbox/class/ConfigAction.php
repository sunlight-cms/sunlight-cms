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
                'input' => _buffer(function () use ($config) { ?>
                    <select name="config[dark_mode]">
                        <option value="" <?= Form::selectOption($config['dark_mode'] === null) ?>><?= _lang('lightbox.cfg.dark_mode.auto') ?></option>
                        <option value="1" <?= Form::selectOption($config['dark_mode'] === true) ?>><?= _lang('global.yes') ?></option>
                        <option value="0" <?= Form::selectOption($config['dark_mode'] === false) ?>><?= _lang('global.no') ?></option>
                    </select>
                    <?php }),
            ],
            'options' => [
                'label' => _lang('lightbox.cfg.options'),
                'input' => '<textarea name="config[options]" class="areasmall">' . _e(Json::encode($config['options'], Json::PRETTY)) . '</textarea><br>'
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
