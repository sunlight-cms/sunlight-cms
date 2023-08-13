<?php

namespace Sunlight\Search\Source;

class FulltextQueryProcessor
{
    function __invoke(string $query, ?string $modifier): string
    {
        if ($modifier !== 'IN BOOLEAN MODE' || preg_match('{[+\\-~()<>*"@]}', $query)) {
            return $query; // not in boolean mode or the query already uses fulltext syntax
        }

        return implode(
            ' ',
            array_map(
                function (string $word) { return $word . '*'; },
                preg_split('{\s+}', $query)
            )
        );
    }
}
