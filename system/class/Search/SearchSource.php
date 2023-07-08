<?php

namespace Sunlight\Search;

abstract class SearchSource
{
    /** @var string */
    private $key;
    /** @var bool */
    private $enabledByDefault = true;
    /** @var int */
    private $limit = 100;

    function __construct(string $key)
    {
        $this->key = $key;
    }

    function getKey(): string
    {
        return $this->key;
    }

    function isEnabledByDefault(): bool
    {
        return $this->enabledByDefault;
    }

    function setEnabledByDefault(bool $enabledByDefault): void
    {
        $this->enabledByDefault = $enabledByDefault;
    }

    function getLimit(): int
    {
        return $this->limit;
    }

    function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    function getLabel(): string
    {
        return _lang("search.{$this->key}");
    }

    /**
     * @return iterable<SearchResult>
     */
    abstract function search(string $query): iterable;
}
