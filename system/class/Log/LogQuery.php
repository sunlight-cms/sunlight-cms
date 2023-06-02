<?php

namespace Sunlight\Log;

class LogQuery
{
    /** @var int|null */
    public $maxLevel;
    /** @var string|null */
    public $category;
    /** @var int|null */
    public $since;
    /** @var int|null */
    public $until;
    /** @var string|null */
    public $keyword;
    /** @var string|null */
    public $method;
    /** @var string|null */
    public $urlKeyword;
    /** @var string|null */
    public $ip;
    /** @var int|null */
    public $userId;
    /** @var bool */
    public $desc = true;
    /** @var int */
    public $offset = 0;
    /** @var int */
    public $limit = 100;
}
