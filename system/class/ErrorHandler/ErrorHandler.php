<?php

namespace Sunlight\ErrorHandler;

use Kuria\Error\ErrorHandler as BaseErrorHandler;

class ErrorHandler extends BaseErrorHandler
{
    function __construct()
    {
        parent::__construct(!$this->isCli() ? new WebErrorScreen() : null);
    }
}
