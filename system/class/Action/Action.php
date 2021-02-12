<?php

namespace Sunlight\Action;

use Sunlight\Core;
use Sunlight\Message;

abstract class Action
{
    /** @var bool */
    protected $catchExceptions = false;
    /** @var bool */
    protected $renderExceptions = false;

    /**
     * Set whether exceptions should be catched
     *
     * @param bool $catchExceptions
     * @return $this
     */
    function setCatchExceptions(bool $catchExceptions): self
    {
        $this->catchExceptions = $catchExceptions;

        return $this;
    }

    /**
     * Set whether exceptions should be rendered
     *
     * @param bool $renderExceptions
     * @return $this
     */
    function setRenderExceptions(bool $renderExceptions): self
    {
        $this->renderExceptions = $renderExceptions;

        return $this;
    }

    /**
     * Run the action
     *
     * @return ActionResult
     */
    function run(): ActionResult
    {
        try {
            $result = $this->execute();

            if (!$result instanceof ActionResult) {
                throw new \UnexpectedValueException(sprintf(
                    'Invalid return value from %s->execute(), expected ActionResult',
                    get_called_class()
                ));
            }
        } catch (\Throwable $e) {
            if ($this->catchExceptions) {
                $result = ActionResult::failure(Message::error(_lang('global.error')));

                if ($this->renderExceptions) {
                    $result->setOutput(Core::renderException($e));
                }
            } else {
                throw $e;
            }
        }

        return $result;
    }

    /**
     * Execute the action
     *
     * @return ActionResult
     */
    abstract protected function execute(): ActionResult;
}
