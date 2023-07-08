<?php

namespace Sunlight\Search\Source;

use Sunlight\Database\Database as DB;
use Sunlight\Router;
use Sunlight\Search\SearchResult;
use Sunlight\User;
use Sunlight\Util\StringManipulator;

class PageSource extends FulltextSource
{
    protected function getTable(): string
    {
        return DB::table('page');
    }

    protected function getFulltextColumns(): array
    {
        return [
            'title',
            'heading',
            'description',
            'search_content',
        ];
    }

    protected function getResultColumns(): array
    {
        return ['id', 'title', 'slug', 'description', 'perex'];
    }

    protected function getFilter(): array
    {
        $filter = [
            'level<=' . User::getLevel(),
        ];

        if (!User::isLoggedIn()) {
            $filter[] = 'public=1';
        }

        return $filter;
    }

    protected function hydrateResult(SearchResult $result, array $row): void
    {
        $result->link = Router::page($row['id'], $row['slug']);
        $result->title = $row['title'];

        if ($row['perex'] !== '') {
            $result->perex = StringManipulator::ellipsis(strip_tags($row['perex']), 255);
        } elseif ($row['description'] !== '') {
            $result->perex = $row['description'];
        }
    }
}
