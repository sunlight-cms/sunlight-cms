<?php

namespace Sunlight\Search\Source;

use Sunlight\Database\Database as DB;
use Sunlight\Gallery;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\Search\SearchResult;
use Sunlight\Settings;
use Sunlight\User;

class ImageSource extends FulltextSource
{
    private $resizeOptions;

    function __construct(string $key)
    {
        parent::__construct($key);

        $this->resizeOptions = [
            'w' => Settings::get('galdefault_thumb_w'),
            'h' => Settings::get('galdefault_thumb_h'),
        ];
    }

    protected function getTable(): string
    {
        return DB::table('gallery_image');
    }

    protected function getTableAlias(): ?string
    {
        return 'img';
    }

    protected function getFulltextColumns(): array
    {
        return ['img.title'];
    }

    protected function getResultColumns(): array
    {
        return [
            'img.id',
            'img.prev',
            'img.full',
            'img.ord',
            'img.home',
            'img.title',
            'gal.title AS gal_title',
            'gal.slug',
            'gal.var2',
        ];
    }

    protected function getJoins(): array
    {
        return ['JOIN ' . DB::table('page') . ' AS gal ON(gal.id=img.home)'];
    }

    protected function getFilter(): array
    {
        $filter = [
            'gal.level<=' . User::getLevel(),
        ];

        if (!User::isLoggedIn()) {
            $filter[] = 'gal.public=1';
        }

        return $filter;
    }

    protected function hydrateResult(SearchResult $result, array $row): void
    {
        $result->link = Router::page($row['home'], $row['slug'], null, [
            'query' => [
                'page' => Paginator::getItemPage($row['var2'] ?: Settings::get('galdefault_per_page'), DB::table('gallery_image'), 'ord<' . $row['ord'] . ' AND home=' . $row['home']),
            ],
        ]);
        $result->title = $row['gal_title'];
        $result->perex = '<p>' . $row['title'] . '</p>'
            . Gallery::renderImage($row, 'search', $this->resizeOptions);
    }
}
