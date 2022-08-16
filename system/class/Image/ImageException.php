<?php

namespace Sunlight\Image;

use Sunlight\Core;

class ImageException extends \RuntimeException
{
    const COULD_NOT_CREATE = 'could-not-create';
    const COULD_NOT_GET_SIZE = 'could-not-get-size';
    const COULD_NOT_LOAD = 'could-not-load';
    const FILE_TOO_BIG = 'file-too-big';
    const FORMAT_NOT_SUPPORTED = 'format-not-supported';
    const IMAGE_TOO_BIG = 'image-too-big';
    const INVALID_ALIGN = 'invalid-align';
    const INVALID_DIMENSIONS = 'invalid-dimensions';
    const INVALID_RESIZE_MODE = 'invalid-resize-mode';
    const MOVE_FAILED = 'move-failed';
    const NOT_ALLOWED = 'not-allowed';
    const NOT_ENOUGH_MEMORY = 'not-enough-memory';
    const NOT_FOUND = 'not-found';
    const RESIZE_FAILED = 'resize-failed';
    const WRITE_FAILED = 'write-failed';

    /** @var string */
    private $reasonCode;
    /** @var string[] */
    private $userFriendlyMessageArgs;
    /** @var string|null */
    private $additionalInformation;

    function __construct(
        string $reasonCode,
        ?array $userFriendlyMessageArgs = null,
        ?string $additionalInformation = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            sprintf(
                'Image operation failed (reason: %s, info: %s)',
                $reasonCode,
                $additionalInformation ?? 'NULL'
            ),
            0,
            $previous
        );

        $this->reasonCode = $reasonCode;
        $this->userFriendlyMessageArgs = $userFriendlyMessageArgs;
        $this->additionalInformation = $additionalInformation;
    }

    function getReasonCode(): string
    {
        return $this->reasonCode;
    }

    function getUserFriendlyMessage(): string
    {
        $message = _lang('image.error.' . $this->reasonCode, $this->userFriendlyMessageArgs);

        // include more info in debug mode
        if (Core::$debug && $this->additionalInformation !== null) {
            $message .= sprintf(' (DEBUG: %s)', $this->additionalInformation);
        }

        return $message;
    }
}
