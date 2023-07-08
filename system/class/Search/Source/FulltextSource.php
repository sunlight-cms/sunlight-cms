<?php

namespace Sunlight\Search\Source;

use Sunlight\Database\Database as DB;
use Sunlight\Search\SearchResult;
use Sunlight\Search\SearchSource;

abstract class FulltextSource extends SearchSource
{
    function search(string $query): iterable
    {
        $alias = $this->getTableAlias();
        $joins = $this->getJoins();
        $filter = $this->getFilter();

        $query = DB::query(
            'SELECT ' . implode(',', $this->getResultColumns())
            . ',MATCH(' . implode(',', $this->getFulltextColumns()) . ') AGAINST(' . DB::val($query) . ') score'
            . ' FROM ' . DB::escIdt($this->getTable()) . ($alias !== null ? ' ' . $alias : '')
            . (!empty($joins) ? ' ' . implode(' ', $joins) : '')
            . (!empty($filter) ? ' WHERE ' . implode(' AND ', $filter) : '')
            . ' HAVING score>0'
            . ' ORDER BY score DESC'
            . ' LIMIT ' . $this->getLimit()
        );

        while ($row = DB::row($query)) {
            $result = new SearchResult();
            $this->hydrateResult($result, $row);

            yield $result;
        }
    }

    abstract protected function getTable(): string;

    protected function getTableAlias(): ?string
    {
        return null;
    }

    /**
     * @return string[]
     */
    abstract protected function getFulltextColumns(): array;

    /**
     * @return string[]
     */
    abstract protected function getResultColumns(): array;

    /**
     * @return string[]
     */
    protected function getJoins(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    protected function getFilter(): array
    {
        return [];
    }

    abstract protected function hydrateResult(SearchResult $result, array $row): void;
}
