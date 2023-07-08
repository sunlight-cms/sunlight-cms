<?php

namespace Sunlight\Search;

class SearchResult
{
    /** @var string */
    public $link;
    /** @var string */
    public $title;
    /** @var string|null */
    public $perex;
    /**
     * @var array
     * @see \Sunlight\GenericTemplates::renderInfos()
     */
    public $infos = [];
}
