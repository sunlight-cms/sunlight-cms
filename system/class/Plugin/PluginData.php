<?php

namespace Sunlight\Plugin;

class PluginData
{
    /** @var string */
    public $id;
    /** @var string */
    public $name;
    /** @var string */
    public $camelCasedName;
    /** @var string */
    public $type;
    /** @var string|null */
    public $status;
    /** @var bool|null */
    public $installed;
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

    function __construct(string $id, string $name, string $camelCasedName, string $type, string $file, string $webPath)
    {
        $this->id = $id;
        $this->name = $name;
        $this->camelCasedName = $camelCasedName;
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
