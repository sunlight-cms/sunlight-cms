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
    function __construct($type, $message, $isHtml = false)
    {
        $this->type = $type;
        $this->message = $message;
        $this->isHtml = $isHtml;
    }

    /**
     * Render a message
     *
     * @see Message::__construct()
     */
    static function render($type, $message, $isHtml = false)
    {
        $message = new Message($type, $message, $isHtml);

        return $message->__toString();
    }

    /**
     * Render a formatted list of messages
     *
     * @param array       $messages    the messages
     * @param string|null $description description ("errors" = _lang('misc.errorlog.intro'), null = none, anything else = custom)
     * @param bool        $showKeys    render $message keys as well
     * @return string
     */
    static function renderList($messages, $description = null, $showKeys = false)
    {
        $output = '';

        if (!empty($messages)) {
            // description
            if ($description != null) {
                if ($description !== 'errors') {
                    $output .= $description;
                } else {
                    $output .= _lang('misc.errorlog.intro');
                }
                $output .= "\n";
            }

            // messages
            $output .= "<ul>\n";
            foreach($messages as $key => $item) {
                $output .= '<li>' . ($showKeys ? '<strong>' . _e($key) . '</strong>: ' : '') . $item . "</li>\n";
            }
            $output .= "</ul>\n";
        }

        return $output;
    }

    /**
     * Create an informational message
     *
     * @param string $message the message
     * @param bool   $isHtml  display the message should be rendered as html (unescaped) 1/0
     * @return static
     */
    static function ok($message, $isHtml = false)
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
    static function warning($message, $isHtml = false)
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
    static function error($message, $isHtml = false)
    {
        return new static(_msg_err, $message, $isHtml);
    }

    /**
     * Render the message
     *
     * @return string
     */
    function __toString()
    {
        $output = Extend::buffer('message.render', array('message' => $this));

        if ($output === '') {
            $output = "\n<div class='message message-" . _e($this->type) . "'>"
                . ($this->isHtml ? $this->message : _e($this->message))
                . "</div>\n";
        }

        return (string) $output;
    }

    /**
     * Get the message type
     *
     * @return string
     */
    function getType()
    {
        return $this->type;
    }

    /**
     * Get the message
     *
     * @return string
     */
    function getMessage()
    {
        return $this->message;
    }

    /**
     * See if the message is HTML
     *
     * @return string
     */
    function isHtml()
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
    function append($str, $isHtml = false)
    {
        if ($this->isHtml) {
            // append to current HTML
            $this->message .= $isHtml ? $str : _e($str);
        } else {
            // append to current plaintext
            if ($isHtml) {
                // convert message to HTML
                $this->message = _e($this->message) . $str;
                $this->isHtml = true;
            } else {
                // append as-is
                $this->message .= $str;
            }
        }
    }
}
