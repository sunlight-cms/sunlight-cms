<?php

namespace SunlightExtend\Lightbox;

use Sunlight\Plugin\Action\ConfigAction as BaseConfigAction;
use Sunlight\Util\ConfigurationFile;
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
                        <option value="" <?= $config['dark_mode'] === null ? ' selected' : '' ?>><?= _lang('lightbox.cfg.dark_mode.auto') ?></option>
                        <option value="1" <?= $config['dark_mode'] === true ? ' selected' : '' ?>><?= _lang('global.yes') ?></option>
                        <option value="0" <?= $config['dark_mode'] === false ? ' selected' : '' ?>><?= _lang('global.no') ?></option>
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
                } catch (\RuntimeException $e) {
                    return $e->getMessage();
                }

                return null;
        }

        return parent::mapSubmittedValue($config, $key, $field, $value);
    }
}
