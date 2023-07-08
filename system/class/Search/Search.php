<?php

namespace Sunlight\Search;

use Sunlight\Extend;

class Search
{
    /** @var array<string, SearchSource> */
    private static $sources;
    /** @var array<string, true> */
    private $enabledSources = [];
    /** @var string */
    private $query = '';

    function toggleSource(string $key, bool $enabled): void
    {
        if ($enabled) {
            $this->enabledSources[$key] = true;
        } else {
            unset($this->enabledSources[$key]);
        }
    }

    function setQuery(string $query): void
    {
        $this->query = $query;
    }

    /**
     * @return iterable<SearchResult>
     */
    function search(): iterable
    {
        if ($this->query === '') {
            return;
        }

        foreach (self::getSources() as $key => $source) {
            if (isset($this->enabledSources[$key])) {
                yield from $source->search($this->query);
            }
        }
    }

    /**
     * @return array<string, SearchSource>
     */
    static function getSources(): array
    {
        if (self::$sources !== null) {
            return self::$sources;
        }

        self::$sources = [
            'pages' => new Source\PageSource('pages'),
            'articles' => new Source\ArticleSource('articles'),
            'posts' => new Source\PostSource('posts'),
            'images' => new Source\ImageSource('images'),
        ];

        Extend::call('search.init_sources', ['sources' => &self::$sources]);

        return self::$sources;
    }
}
