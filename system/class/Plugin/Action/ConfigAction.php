<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;
use Sunlight\Plugin\Plugin;
use Sunlight\Util\ConfigurationFile;
use Sunlight\Util\Form;
use Sunlight\Util\StringHelper;

class ConfigAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.config');
    }

    function isAllowed(): bool
    {
        return $this->plugin->hasStatus(Plugin::STATUS_OK) && $this->plugin->hasConfig();
    }

    protected function execute(): ActionResult
    {
        $messages = [];

        if (isset($_POST['reset'])) {
            $this->plugin->getConfig()->reset();
            $this->plugin->getConfig()->save();
            $messages[] = Message::ok(_lang('global.done'));
        }

        $fields = $this->getFields();

        if (isset($_POST['save'])) {
            $config = $this->plugin->getConfig();
            $submittedConfig = isset($_POST['config']) && is_array($_POST['config'])
                ? $_POST['config']
                : [];
            $errors = [];

            foreach ($fields as $key => $field) {
                $submittedValue = $submittedConfig[$key] ?? null;

                if (($error = $this->mapSubmittedValue($config, $key, $field, $submittedValue)) !== null) {
                    $errors[] = Message::prefix($field['label'], $error);
                }
            }

            if (empty($errors)) {
                $config->save();
                $messages[] = Message::ok(_lang('global.saved'));
            } else {
                $messages[] = Message::list($errors, ['type' => Message::ERROR]);
            }

            $fields = $this->getFields();
        }

        return ActionResult::output(_buffer(function () use ($fields) { ?>
<?= Form::start('plugin-config') ?>
    <table class="list table-collapse valign-top">
        <?php foreach ($fields as $field): ?>
            <tr>
                <th><?= $field['label'] ?></th>
                <td><?= $field['input'] ?></td>
            </tr>
        <?php endforeach ?>
        <tr>
            <th></th>
            <td>
                <?= Form::input('submit', 'save', _lang('global.save')) ?>
                <?= Form::input('submit', 'reset', _lang('global.default', ['onclick' => 'return Sunlight.confirm();'])) ?>
            </td>
        </tr>
    </table>
<?= Form::end('plugin-config') ?>
<?php
        }), $messages);
    }

    protected function getFields(): array
    {
        $fields = [];

        foreach ($this->plugin->getConfig()->toArray() as $key => $value) {
            if (!is_scalar($value) && !is_null($value)) {
                continue;
            }

            $id = "plugin_config_{$key}";

            if (is_bool($value)) {
                $input = Form::input('checkbox', 'config[' . $key . ']', '1', ['id' => $id, 'checked' => $value]);
                $type = 'checkbox';
            } else {
                $input = Form::input('text', 'config[' . $key . ']', $value, ['id' => $id, 'class' => 'inputmedium']);
                $type = 'text';
            }

            $fields[$key] = [
                'label' => '<label for="' . _e($id) . '">' . $this->getConfigLabel($key) . '</label>',
                'input' => $input,
                'type' => $type,
            ];
        }

        return $fields;
    }

    function getConfigLabel(string $key): string
    {
        return StringHelper::ucfirst(strtr($key, '_.-', '   '));
    }

    /**
     * Map a submitted value to the config
     *
     * Returns NULL on success or an error message on failure.
     */
    protected function mapSubmittedValue(ConfigurationFile $config, string $key, array $field, $value): ?string
    {
        if (isset($field['type'])) {
            switch ($field['type']) {
                case 'checkbox':
                    $config[$key] = (bool) $value;
                    return null;
                case 'text':
                    if (!is_string($value)) {
                        return _lang('global.badinput');
                    }

                    $value = trim($value);

                    if ($value === '') {
                        $value = null;
                    }

                    $config[$key] = $value;
                    return null;
            }
        }

        return _lang('admin.plugins.action.config.no_map');
    }
}
