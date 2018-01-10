<?php

namespace Sunlight\Plugin\Action;

use Kuria\Debug\Dumper;
use Sunlight\Action\ActionResult;
use Sunlight\Message;

/**
 * Modify plugin configuration
 */
class ConfigAction extends PluginAction
{
    public function getTitle()
    {
        return _lang('admin.plugins.action.do.config');
    }

    protected function execute()
    {
        $messages = array();

        $fields = $this->getFields();

        if (isset($_POST['config']) && is_array($_POST['config'])) {
            foreach ($fields as $key => $field) {
                if (isset($_POST['config'][$key])) {
                    $submittedValue = $_POST['config'][$key];
                } else {
                    $submittedValue = null;
                }

                $this->mapSubmittedValue($key, $field, $submittedValue);
            }

            $this->plugin->getConfig()->save();
            $messages[] = Message::ok(_lang('global.saved'));
            $fields = $this->getFields();
        }

        return ActionResult::output(_buffer(function () use ($fields) { ?>
<form method="POST">
    <table class="list valign-top">
        <?php foreach ($fields as $field): ?>
            <tr>
                <th><?php echo $field['label'] ?></th>
                <td><?php echo $field['input'] ?></td>
            </tr>
        <?php endforeach ?>
        <tr>
            <th></th>
            <td><input type="submit" value="<?php echo _lang('global.save') ?>"></td>
        </tr>
    </table>

    <?php echo _xsrfProtect() ?>
</form>
<?php
        }), $messages);
    }

    /**
     * @return array
     */
    protected function getFields()
    {
        $fields = array();

        foreach ($this->plugin->getConfig()->toArray() as $key => $value) {
            if (!is_scalar($value) && !is_null($value)) {
                continue;
            }

            if (is_bool($value)) {
                $input = '<input type="checkbox" name="config[' . _e($key) . ']" value="1"' . _checkboxActivate($value) . '>';
                $type = 'checkbox';
            } else {
                $input = '<input type="text" name="config[' . _e($key) . ']" class="inputmedium" value="' . _e($value) . '">';
                $type = 'text';
            }

            $fields[$key] = array(
                'label' => $this->plugin->getConfigLabel($key),
                'input' => $input,
                'type' => $type,
            );
        }

        return $fields;
    }

    /**
     * @param string $key
     * @param array $field
     * @param mixed $value
     */
    protected function mapSubmittedValue($key, array $field, $value)
    {
        $config = $this->plugin->getConfig();

        switch ($field['type']) {
            case 'checkbox':
                $config[$key] = (bool) $value;
                break;

            case 'text':
                if (!is_string($value)) {
                    break;
                }

                $value = trim($value);

                if ($value === '') {
                    $value = null;
                } elseif (ctype_digit($value)) {
                    $value = (int) $value;
                }

                $config[$key] = $value;
                break;
        }
    }
}
