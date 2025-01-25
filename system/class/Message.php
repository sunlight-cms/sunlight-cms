<?php

namespace Sunlight;

use Sunlight\Util\StringHelper;

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
    /** @var bool */
    private $classes;

    /**
     * @param string $type see class constants
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     * @param string[]|null $classes additional classes
     */
    function __construct(string $type, string $message, bool $isHtml = false, ?array $classes = null)
    {
        $this->type = $type;
        $this->message = $message;
        $this->isHtml = $isHtml;
        $this->classes = $classes;
    }

    /**
     * Render a message
     *
     * @see __construct()
     * @param string[]|null $classes additional classes
     */
    static function render(string $type, string $message, bool $isHtml = false, ?array $classes = null): string
    {
        $message = new self($type, $message, $isHtml, $classes);

        return $message->__toString();
    }

    /**
     * Create an informational message
     *
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     * @param string[]|null $classes additional classes
     */
    static function ok(string $message, bool $isHtml = false, ?array $classes = null): self
    {
        return new self(self::OK, $message, $isHtml, $classes);
    }

    /**
     * Create a warning message
     *
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     * @param string[]|null $classes additional classes
     */
    static function warning(string $message, bool $isHtml = false, ?array $classes = null): self
    {
        return new self(self::WARNING, $message, $isHtml, $classes);
    }

    /**
     * Create an error message
     *
     * @param string $message the message
     * @param bool $isHtml display the message should be rendered as html (unescaped) 1/0
     * @param string[]|null $classes additional classes
     */
    static function error(string $message, bool $isHtml = false, ?array $classes = null): self
    {
        return new self(self::ERROR, $message, $isHtml, $classes);
    }

    /**
     * List messages
     *
     * Supported options:
     * ------------------
     * - type (warn)  see Message class constants
     * - text         content at the beginning of the message
     * - list         options for {@see GenericTemplates::renderMessageList()}
     * - classes      additional classes
     *
     * @param string[] $messages
     * @param array{
     *     type?: string,
     *     text?: string,
     *     list?: array{lcfirst?: bool|null, trim_dots?: bool|null, escape?: bool|null, show_keys?: bool|null},
     *     classes?: string[],
     * } $options see description
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
        return $prefix . ': ' . StringHelper::lcfirst($message);
    }

    /**
     * Render the message
     */
    function __toString(): string
    {
        $output = Extend::buffer('message.render', ['message' => $this]);

        if ($output === '') {
            $output = "\n<div class=\"message message-" . _e($this->type) . (!empty($this->classes) ? ' ' . _e(implode(' ', $this->classes)) : '') . '">'
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
