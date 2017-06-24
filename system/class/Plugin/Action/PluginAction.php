<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\Action;
use Sunlight\Action\ActionResult;
use Sunlight\Plugin\Plugin;

/**
 * Plugin action
 */
abstract class PluginAction extends Action
{
    /** @var Plugin */
    protected $plugin;

    /**
     * @param Plugin $plugin
     * @throws \RuntimeException if instantiated outside of administration environment
     */
    public function __construct(Plugin $plugin)
    {
        if (!_env_admin) {
            throw new \RuntimeException('Plugin actions require administration environment');
        }

        $this->plugin = $plugin;
        $this->catchExceptions = true;
        $this->renderExceptions = true;
    }

    /**
     * Get title of the action
     *
     * @return string
     */
    abstract public function getTitle();

    /**
     * See if the action has been confirmed
     *
     * @return bool
     */
    protected function isConfirmed()
    {
        return _post('_plugin_action_confirmation') === md5(get_called_class());
    }

    /**
     * Confirm an action
     *
     * @param string      $message
     * @param string|null $buttonText
     * @return ActionResult
     */
    protected function confirm($message, $buttonText = null)
    {
        if ($buttonText === null) {
            $buttonText = $GLOBALS['_lang']['global.continue'];
        }

        $confirmationToken = md5(get_called_class());

        return ActionResult::output(_buffer(function () use (
            $message,
            $buttonText,
            $confirmationToken
        ) {
            ?>
<form method="post">
    <input type="hidden" name="_plugin_action_confirmation" value="<?php echo $confirmationToken ?>">

    <p class="bborder"><?php echo $message ?></p>

    <input type="submit" value="<?php echo $buttonText ?>">
    <?php echo _xsrfProtect() ?>
</form>
<?php
        }));
    }
}
