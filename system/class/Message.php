<?php

namespace Sunlight;

use Sunlight\Util\StringManipulator;

/**
 * System message
 */
class Message
{
    const OK = 'ok';
    const WARNING = 'warn';
    const ERROR = 'err';

    /** @var string */
    private $type;
    /** @var string */
    private $message;
    /** @var bool */
    private $isHtml;

    /**
     * @param string $type see class constants
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     */
    function __construct(string $type, string $message, bool $isHtml = false)
    {
        $this->type = $type;
        $this->message = $message;
        $this->isHtml = $isHtml;
    }

    /**
     * Render a message
     *
     * @see __construct()
     */
    static function render(string $type, string $message, bool $isHtml = false): string
    {
        $message = new self($type, $message, $isHtml);

        return $message->__toString();
    }

    /**
     * Create an informational message
     *
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     */
    static function ok(string $message, bool $isHtml = false): self
    {
        return new self(self::OK, $message, $isHtml);
    }

    /**
     * Create a warning message
     *
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     */
    static function warning(string $message, bool $isHtml = false): self
    {
        return new self(self::WARNING, $message, $isHtml);
    }

    /**
     * Create an error message
     *
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     */
    static function error(string $message, bool $isHtml = false): self
    {
        return new self(self::ERROR, $message, $isHtml);
    }

    /**
     * List messages
     *
     * Supported options:
     * ---------------------------------------------------------------------------
     * type (WARNING)   see Message class constants
     * text             text before the list
     * list             options for {@see GenericTemplates::renderMessageList()}
     */
    static function list(array $messages, array $options = []): self
    {
        return new self(
            $options['type'] ?? self::WARNING,
            ($options['text'] ?? _lang('error.list_text'))
                . "\n"
                . GenericTemplates::renderMessageList($messages, $options['list'] ?? []),
            true
        );
    }

    /**
     * Prefix a message
     */
    static function prefix(string $prefix, string $message): string
    {
        return $prefix . ': ' . StringManipulator::lcfirst($message);
    }

    /**
     * Render the message
     */
    function __toString(): string
    {
        $output = Extend::buffer('message.render', ['message' => $this]);

        if ($output === '') {
            $output = "\n<div class=\"message message-" . _e($this->type) . '">'
                . ($this->isHtml ? $this->message : _e($this->message))
                . "</div>\n";
        }

        return $output;
    }

    /**
     * Get the message type
     */
    function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the message
     */
    function getMessage(): string
    {
        return $this->message;
    }

    /**
     * See if the message is HTML
     */
    function isHtml(): bool
    {
        return $this->isHtml;
    }

    /**
     * Append a string to the message
     *
     * This forces the message to become HTML, if it isn't already
     */
    function append(string $str, bool $isHtml = false): void
    {
        if ($this->isHtml) {
            // append to current HTML
            $this->message .= $isHtml ? $str : _e($str);
        } elseif ($isHtml) {
            // convert message to HTML
            $this->message = _e($this->message) . $str;
            $this->isHtml = true;
        } else {
            // append as-is
            $this->message .= $str;
        }
    }
}
