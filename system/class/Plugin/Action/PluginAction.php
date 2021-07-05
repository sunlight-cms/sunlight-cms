<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\Action;
use Sunlight\Action\ActionResult;
use Sunlight\Core;
use Sunlight\Plugin\Plugin;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

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
    function __construct(Plugin $plugin)
    {
        if (Core::$env !== Core::ENV_ADMIN) {
            throw new \RuntimeException('Plugin actions require administration environment');
        }

        $this->plugin = $plugin;
        $this->setCatchExceptions(true);
        $this->setRenderExceptions(true);
    }

    /**
     * Get title of the action
     *
     * @return string
     */
    abstract function getTitle(): string;

    /**
     * See if the action has been confirmed
     *
     * @return bool
     */
    protected function isConfirmed(): bool
    {
        return Request::post('_plugin_action_confirmation') === md5(static::class);
    }

    /**
     * Confirm an action
     *
     * @param string      $message
     * @param string|null $buttonText
     * @return ActionResult
     */
    protected function confirm(string $message, ?string $buttonText = null): ActionResult
    {
        if ($buttonText === null) {
            $buttonText = _lang('global.continue');
        }

        $confirmationToken = md5(static::class);

        return ActionResult::output(_buffer(function () use (
            $message,
            $buttonText,
            $confirmationToken
        ) {
            ?>
<form method="post">
    <input type="hidden" name="_plugin_action_confirmation" value="<?= $confirmationToken ?>">

    <p class="bborder"><?= $message ?></p>

    <input type="submit" value="<?= $buttonText ?>">
    <?= Xsrf::getInput() ?>
</form>
<?php
        }));
    }
}
