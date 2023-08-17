<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\Action;
use Sunlight\Action\ActionResult;
use Sunlight\Core;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Plugin\Plugin;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

abstract class PluginAction extends Action
{
    /** @var Plugin */
    protected $plugin;

    /**
     * @throws \RuntimeException if instantiated outside of admin environment
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
     */
    abstract function getTitle(): string;

    /**
     * See if the action is allowed to take place
     */
    abstract function isAllowed(): bool;

    function run(): ActionResult
    {
        if (!$this->isAllowed()) {
            return ActionResult::output(null, Message::error(_lang('global.accessdenied')));
        }

        $result = parent::run();

        if ($result->isComplete() || $result->hasMessages()) {
            Logger::notice(
                'system',
                sprintf('Executed action "%s" on plugin "%s"', (new \ReflectionClass($this))->getShortName(), $this->plugin->getId()),
                [
                    'plugin' => $this->plugin->getId(),
                    'action' => get_class($this),
                    'result' => $result->getResult(),
                    'messages' => array_map(
                        function (Message $message) {
                            return $message->getMessage();
                        },
                        $result->getMessages()
                    ),
                ]
            );
        }

        return $result;
    }

    /**
     * See if the action has been confirmed
     */
    protected function isConfirmed(): bool
    {
        return Request::post('_plugin_action_confirmation') === md5(static::class);
    }

    /**
     * Confirm an action
     *
     * Supported options:
     * ------------------
     * - button_text      customize button text
     * - content_before   HTML before text
     * - content_after    HTML after text
     *
     * @param array{
     *     button_text?: string|null,
     *     content_before?: string|null,
     *     content_after?: string|null,
     * } $options see description
     */
    protected function confirm(string $text, array $options = []): ActionResult
    {
        $confirmationToken = md5(static::class);

        return ActionResult::output(_buffer(function () use (
            $text,
            $options,
            $confirmationToken
        ) {
            ?>
<form method="post">
    <input type="hidden" name="_plugin_action_confirmation" value="<?= $confirmationToken ?>">

    <?= $options['content_before'] ?? '' ?>
    <p class="bborder"><?= $text ?></p>
    <?= $options['content_after'] ?? '' ?>

    <input type="submit" value="<?= $options['button_text'] ?? _lang('global.continue') ?>">
    <?= Xsrf::getInput() ?>
</form>
<?php
        }));
    }

    protected function getDependantsWarning(): ?Message
    {
        $dependants = Core::$pluginManager->getDependants($this->plugin);

        if (!empty($dependants)) {
            return Message::list(
                array_map(
                    function (Plugin $dependant) { return $dependant->getOption('name'); },
                    $dependants
                ),
                [
                    'text' => _lang('admin.plugins.action.dependants_warning'),
                    'list' => ['lcfirst' => false],
                ]
            );
        }

        return null;
    }
}
