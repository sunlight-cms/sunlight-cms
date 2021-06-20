<?php

namespace Sunlight\Plugin;

use Sunlight\Util\StringManipulator;

class PluginData
{
    /** @var string */
    public $id;
    /** @var string */
    public $camelId;
    /** @var string */
    public $type;
    /** @var int|null */
    public $status;
    /** @var bool */
    public $installed = null;
    /** @var string */
    public $dir;
    /** @var string */
    public $file;
    /** @var string */
    public $webPath;
    /** @var string[] */
    public $errors = [];
    /** @var array */
    public $options = [];

    function __construct(string $id, string $type, string $file, string $webPath)
    {
        $this->id = $id;
        $this->camelId = StringManipulator::toCamelCase($id);
        $this->type = $type;
        $this->dir = dirname($file);
        $this->file = $file;
        $this->webPath = $webPath;
        $this->options = [
            'name' => $id,
            'version' => '0.0.0',
        ];
    }

    function isOk(): bool
    {
        return $this->status === Plugin::STATUS_OK;
    }

    function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    function addError(string ...$errors): void
    {
        if ($errors) {
            array_push($this->errors, ...$errors);
        }
    }
}
