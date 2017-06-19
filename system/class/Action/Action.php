<?php

namespace Sunlight\Action;

use Sunlight\Core;
use Sunlight\Message;

abstract class Action
{
    /** @var ActionResult|null */
    protected $result;
    /** @var bool */
    protected $catchExceptions = false;
    /** @var bool */
    protected $renderExceptions = false;

    /**
     * Set whether exceptions should be catched
     *
     * @param bool $catchExceptions
     * @return static
     */
    public function setCatchExceptions($catchExceptions)
    {
        $this->catchExceptions = $catchExceptions;

        return $this;
    }

    /**
     * Set whether exceptions should be rendered
     *
     * @param bool $renderExceptions
     * @return static
     */
    public function setRenderExceptions($renderExceptions)
    {
        $this->renderExceptions = $renderExceptions;

        return $this;
    }

    /**
     * Run the action
     *
     * @return ActionResult
     */
    public function run()
    {
        $this->result = null;

        $e = null;
        try {
            $result = $this->execute();

            if (!$result instanceof ActionResult) {
                throw new \UnexpectedValueException(sprintf(
                    'Invalid return value from %s->execute(), expected ActionResult',
                    get_called_class()
                ));
            }

            return $result;
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        if (null !== $e) {
            if ($this->catchExceptions) {
                $result = ActionResult::failure(Message::error($_lang['global.error']));

                if ($this->renderExceptions) {
                    $result->setOutput(Core::renderException($e));
                }
            } else {
                throw $e;
            }
        }
    }

    /**
     * Execute the action
     *
     * @return ActionResult
     */
    abstract protected function execute();
}
