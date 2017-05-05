<?php

namespace Sunlight;

/**
 * Core exception
 *
 * Its message is publicly displayed to the user even in production mode (unlike other exceptions).
 *
 * Created by {@see Core::systemFailure()}
 */
class CoreException extends \Exception
{
}
