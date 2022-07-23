<?php

namespace Sunlight\ErrorHandler;

use Kuria\Error\ErrorHandler as BaseErrorHandler;

class ErrorHandler extends BaseErrorHandler
{
    public function __construct()
    {
        parent::__construct(!$this->isCli() ? new WebErrorScreen() : null);
    }
}
