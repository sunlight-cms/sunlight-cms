<?php

namespace Sunlight\Search\Source;

use Sunlight\Database\Database as DB;
use Sunlight\Search\SearchResult;
use Sunlight\Search\SearchSource;

/**
 * @psalm-type ModifierString = 'IN NATURAL LANGUAGE MODE'|'IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION'|'IN BOOLEAN MODE'|'WITH QUERY EXPANSION'|null
 */
abstract class FulltextSource extends SearchSource
{
    /** @var ModifierString */
    private $modifier = 'IN BOOLEAN MODE';
    /** @var callable|null */
    private $queryProcessor;
    /** @var float */
    private $scoreThreshold = 0.0;

    function __construct(string $key)
    {
        parent::__construct($key);

        $this->queryProcessor = new FulltextQueryProcessor();
    }

    function search(string $query): iterable
    {
        $alias = $this->getTableAlias();
        $joins = $this->getJoins();
        $filter = $this->getFilter();

        if ($this->queryProcessor !== null) {
            $query = ($this->queryProcessor)($query, $this->modifier);
        }

        $query = DB::query(
            'SELECT ' . implode(',', $this->getResultColumns())
            . ',MATCH(' . implode(',', $this->getFulltextColumns()) . ')'
            . ' AGAINST(' . DB::val($query) . ($this->modifier !== null ? ' ' . $this->modifier : null) . ') score'
            . ' FROM ' . DB::escIdt($this->getTable()) . ($alias !== null ? ' ' . $alias : '')
            . (!empty($joins) ? ' ' . implode(' ', $joins) : '')
            . (!empty($filter) ? ' WHERE ' . implode(' AND ', $filter) : '')
            . ' HAVING score>' . DB::val($this->scoreThreshold)
            . ' ORDER BY score DESC'
            . ' LIMIT ' . $this->getLimit(),
            true // boolean mode can fail with a syntax error
        );

        if ($query === false) {
            return;
        }

        while ($row = DB::row($query)) {
            $result = new SearchResult();
            $this->hydrateResult($result, $row);

            yield $result;
        }
    }

    /**
     * Set the full-text search modifier
     * 
     * @param ModifierString $modifier
     */
    function setModifier(?string $modifier): void
    {
        $this->modifier = $modifier;
    }

    function setQueryProcessor(?callable $processor): void
    {
        $this->queryProcessor = $processor;
    }

    /**
     * Set the exclusive minimal result score
     */
    function setScoreThreshold(float $scoreThreshold): void
    {
        $this->scoreThreshold = $scoreThreshold;
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
