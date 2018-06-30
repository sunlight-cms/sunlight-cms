<?php

namespace Sunlight\Action;

use Sunlight\Message;

class ActionResult
{
    /** @var bool */
    protected $result;
    /** @var Message[] */
    protected $messages;
    /** @var string|null */
    protected $output;

    /**
     * @param bool|null              $result
     * @param Message|Message[]|null $messages
     * @param string|null            $output
     */
    function __construct($result = null, $messages = null, $output = null)
    {
        if ($messages !== null) {
            if (!is_array($messages)) {
                $messages = array($messages);
            }
        } else {
            $messages = array();
        }

        $this->result = $result;
        $this->messages = $messages;
        $this->output = $output;
    }

    /**
     * Create an intermediate result
     *
     * @param string|null $output
     * @param Message|Message[]|null $messages
     * @return static
     */
    static function output($output, $messages = null)
    {
        return new static(null, $messages, $output);
    }

    /**
     * Create a successful result
     *
     * @param Message|Message[]|null $messages
     * @return static
     */
    static function success($messages = null)
    {
        if (empty($messages)) {
            $messages = Message::ok(_lang('action.success'));
        }

        return new static(true, $messages);
    }

    /**
     * Create an unsuccessful result
     *
     * @param Message|Message[]|null $messages
     * @return static
     */
    static function failure($messages = null)
    {
        if (empty($messages)) {
            $messages = Message::ok(_lang('action.failure'));
        }

        return new static(false, $messages);
    }

    /**
     * Render the action result
     *
     * @return string
     */
    function __toString()
    {
        return join($this->messages) . $this->output;
    }

    /**
     * See if the action is complete
     *
     * @return bool
     */
    function isComplete()
    {
        return $this->result !== null;
    }

    /**
     * See if the action is successful
     *
     * @return bool
     */
    function isSuccessful()
    {
        return $this->result === true;
    }

    /**
     * Get result
     *
     * @return bool|null
     */
    function getResult()
    {
        return $this->result;
    }

    /**
     * Set result
     *
     * @param bool|null $result
     * @return $this
     */
    function setResult($result)
    {
        $this->result = $result;

        return $this;
    }

    /**
     * See if there are any messages
     *
     * @return bool
     */
    function hasMessages()
    {
        return !empty($this->messages);
    }

    /**
     * Get messages
     *
     * @return Message[]
     */
    function getMessages()
    {
        return $this->messages;
    }

    /**
     * Set the messages
     *
     * @param Message[] $messages
     * @return $this
     */
    function setMessages(array $messages)
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Add a message
     *
     * @param Message $message
     * @return $this
     */
    function addMessage(Message $message)
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * See if there is any output
     *
     * @return bool
     */
    function hasOutput()
    {
        return $this->output !== null && $this->output !== '';
    }

    /**
     * Get output
     *
     * @return string|null
     */
    function getOutput()
    {
        return $this->output;
    }

    /**
     * Set output
     *
     * @param string|null $output
     * @return $this
     */
    function setOutput($output)
    {
        $this->output = $output;

        return $this;
    }
}
