<?php

namespace Sunlight\Action;

use Sunlight\Message;

class ActionResult
{
    /** @var bool */
    private $result;
    /** @var Message[] */
    private $messages;
    /** @var string|null */
    private $output;

    /**
     * @param bool|null              $result
     * @param Message|Message[]|null $messages
     * @param string|null            $output
     */
    function __construct(?bool $result = null, $messages = null, ?string $output = null)
    {
        if ($messages !== null) {
            if (!is_array($messages)) {
                $messages = [$messages];
            }
        } else {
            $messages = [];
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
     * @return self
     */
    static function output(?string $output, $messages = null): self
    {
        return new self(null, $messages, $output);
    }

    /**
     * Create a successful result
     *
     * @param Message|Message[]|null $messages
     * @return self
     */
    static function success($messages = null): self
    {
        if (empty($messages)) {
            $messages = Message::ok(_lang('action.success'));
        }

        return new self(true, $messages);
    }

    /**
     * Create an unsuccessful result
     *
     * @param Message|Message[]|null $messages
     * @return self
     */
    static function failure($messages = null): self
    {
        if (empty($messages)) {
            $messages = Message::ok(_lang('action.failure'));
        }

        return new self(false, $messages);
    }

    /**
     * Render the action result
     *
     * @return string
     */
    function __toString(): string
    {
        return implode($this->messages) . $this->output;
    }

    /**
     * See if the action is complete
     *
     * @return bool
     */
    function isComplete(): bool
    {
        return $this->result !== null;
    }

    /**
     * See if the action is successful
     */
    function isSuccessful(): bool
    {
        return $this->result === true;
    }

    /**
     * Get result
     */
    function getResult(): ?bool
    {
        return $this->result;
    }

    /**
     * Set result
     */
    function setResult(?bool $result): void
    {
        $this->result = $result;
    }

    /**
     * See if there are any messages
     *
     * @return bool
     */
    function hasMessages(): bool
    {
        return !empty($this->messages);
    }

    /**
     * Get messages
     *
     * @return Message[]
     */
    function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Set the messages
     *
     * @param Message[] $messages
     */
    function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    /**
     * Add a message
     */
    function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * See if there is any output
     *
     * @return bool
     */
    function hasOutput(): bool
    {
        return $this->output !== null && $this->output !== '';
    }

    /**
     * Get output
     *
     * @return string|null
     */
    function getOutput(): ?string
    {
        return $this->output;
    }

    /**
     * Set output
     */
    function setOutput(?string $output): void
    {
        $this->output = $output;
    }
}
