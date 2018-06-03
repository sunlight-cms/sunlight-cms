<?php

namespace Sunlight\Exception;

/**
 * Core exception
 *
 * Its message is publicly displayed to the user even in production mode (unlike other exceptions).
 *
 * @see Core::systemFailure()
 */
class CoreException extends \Exception
{
}
