<?php

namespace Sunlight\Search\Source;

use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Router;
use Sunlight\Search\SearchResult;
use Sunlight\Util\StringManipulator;

class ArticleSource extends FulltextSource
{
    /** @var string */
    private $joins;
    /** @var string */
    private $filter;

    function __construct(string $key)
    {
        parent::__construct($key);

        [$this->joins, $this->filter] = Article::createFilter('art', [], null, false, true, false);
    }

    protected function getTable(): string
    {
        return DB::table('article');
    }

    protected function getTableAlias(): ?string
    {
        return 'art';
    }

    protected function getFulltextColumns(): array
    {
        return [
            'art.title',
            'art.description',
            'art.search_content',
        ];
    }

    protected function getResultColumns(): array
    {
        return [
            'art.id',
            'art.title',
            'art.slug',
            'art.time',
            'art.perex',
            'cat1.slug AS cat_slug',
        ];
    }

    protected function getJoins(): array
    {
        return [$this->joins];
    }

    protected function getFilter(): array
    {
        return [$this->filter];
    }

    protected function hydrateResult(SearchResult $result, array $row): void
    {
        $result->link = Router::article($row['id'], $row['slug'], $row['cat_slug']);
        $result->title = $row['title'];
        $result->perex = StringManipulator::ellipsis(strip_tags($row['perex']), 255);
        $result->infos[] = [_lang('article.posted'), GenericTemplates::renderDate($row['time'], 'article')];
    }
}
