<?php

namespace Sunlight\Log;

class LogEntry
{
    /** @var string|int entry ID */
    public $id;
    /** @var int */
    public $level;
    /** @var string */
    public $category;
    /** @var int time when the entry was made */
    public $time;
    /** @var string the log message */
    public $message;
    /** @var string|null current request method */
    public $method;
    /** @var string|null current URL */
    public $url;
    /** @var string|null client IP address */
    public $ip;
    /** @var string|null user-agent string */
    public $userAgent;
    /** @var int|null ID of logged-in user when the entry was logged */
    public $userId;
    /** @var string|null JSON data or NULL */
    public $context;
}
