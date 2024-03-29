<?php

namespace Sunlight\Plugin;

use Sunlight\Util\StringHelper;

class PluginData
{
    /** @var string */
    public $type;
    /** @var string */
    public $id;
    /** @var string */
    public $name;
    /** @var string */
    public $camelCasedName;
    /** @var string */
    public $dir;
    /** @var string */
    public $file;
    /** @var string */
    public $webPath;
    /** @var string|null */
    public $status;
    /** @var bool|null */
    public $installed;
    /** @var bool */
    public $vendor = false;
    /** @var string[] */
    public $errors = [];
    /** @var array|null */
    public $options;

    function __construct(string $type, string $id, string $name, string $file, string $webPath)
    {
        $this->type = $type;
        $this->id = $id;
        $this->name = $name;
        $this->camelCasedName = StringHelper::toCamelCase($name);
        $this->dir = dirname($file);
        $this->file = $file;
        $this->webPath = $webPath;
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
