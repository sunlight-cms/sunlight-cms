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
    public function __construct($result = null, $messages = null, $output = null)
    {
        if (null !== $messages) {
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
     * @return static
     */
    public static function output($output)
    {
        return new static(null, array(), $output);
    }

    /**
     * Create a successful result
     *
     * @param Message|Message[]|null $messages
     * @return static
     */
    public static function success($messages = null)
    {
        if (empty($messages)) {
            $messages = Message::ok($GLOBALS['_lang']['action.success']);
        }

        return new static(true, $messages);
    }

    /**
     * Create an unsuccessful result
     *
     * @param Message|Message[]|null $messages
     * @return static
     */
    public static function failure($messages = null)
    {
        if (empty($messages)) {
            $messages = Message::ok($GLOBALS['_lang']['action.failure']);
        }

        return new static(false, $messages);
    }

    /**
     * Render the action result
     *
     * @return string
     */
    public function __toString()
    {
        return join($this->messages) . $this->output;
    }

    /**
     * See if the action is complete
     *
     * @return bool
     */
    public function isComplete()
    {
        return null !== $this->result;
    }

    /**
     * See if the action is successful
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return true === $this->result;
    }

    /**
     * Get result
     *
     * @return bool|null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Set result
     *
     * @param bool|null $result
     * @return static
     */
    public function setResult($result)
    {
        $this->result = $result;

        return $this;
    }

    /**
     * See if there are any messages
     *
     * @return bool
     */
    public function hasMessages()
    {
        return !empty($this->messages);
    }

    /**
     * Get messages
     *
     * @return Message[]
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Set the messages
     *
     * @param Message[] $messages
     * @return static
     */
    public function setMessages(array $messages)
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Add a message
     *
     * @param Message $message
     * @return static
     */
    public function addMessage(Message $message)
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * See if there is any output
     *
     * @return bool
     */
    public function hasOutput()
    {
        return null !== $this->output && '' !== $this->output;
    }

    /**
     * Get output
     *
     * @return string|null
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set output
     *
     * @param string|null $output
     * @return static
     */
    public function setOutput($output)
    {
        $this->output = $output;

        return $this;
    }
}
