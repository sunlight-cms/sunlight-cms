<?php

namespace Sunlight;

/**
 * System message
 */
class Message
{
    /** @var string */
    protected $type;
    /** @var string */
    protected $message;
    /** @var bool */
    protected $isHtml;

    /**
     * @param string $type    see _msg_* constants
     * @param string $message the message
     * @param bool   $isHtml  display the message should be rendered as html (unescaped) 1/0
     */
    public function __construct($type, $message, $isHtml = false)
    {
        $this->type = $type;
        $this->message = $message;
        $this->isHtml = $isHtml;
    }

    /**
     * Create an informational message
     *
     * @param string $message the message
     * @param bool   $isHtml  display the message should be rendered as html (unescaped) 1/0
     * @return static
     */
    public static function ok($message, $isHtml = false)
    {
        return new static(_msg_ok, $message, $isHtml);
    }

    /**
     * Create a warning message
     *
     * @param string $message the message
     * @param bool   $isHtml  display the message should be rendered as html (unescaped) 1/0
     * @return static
     */
    public static function warning($message, $isHtml = false)
    {
        return new static(_msg_warn, $message, $isHtml);
    }

    /**
     * Create an error message
     *
     * @param string $message the message
     * @param bool   $isHtml  display the message should be rendered as html (unescaped) 1/0
     * @return static
     */
    public static function error($message, $isHtml = false)
    {
        return new static(_msg_err, $message, $isHtml);
    }

    /**
     * Render the message
     *
     * @return string
     */
    public function __toString()
    {
        return _msg($this->type, $this->isHtml ? $this->message : _e($this->message));
    }

    /**
     * Get the message type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get the message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * See if the message is HTML
     *
     * @return string
     */
    public function isHtml()
    {
        return $this->isHtml;
    }

    /**
     * Append a string to the message
     *
     * This forces the message to become HTML, if it isn't already
     *
     * @param string $str
     * @param bool   $isHtml
     */
    public function append($str, $isHtml = false)
    {
        $this->forceHtml();

        $this->message .= $isHtml ? $str : _e($str);
    }

    /**
     * Force the message to become HTML, if it isn't already
     */
    protected function forceHtml()
    {
        if (!$this->isHtml) {
            $this->message = _e($this->message);
            $this->isHtml = true;
        }
    }
}
