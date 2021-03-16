<?php

namespace Sunlight\Database;

use Sunlight\Util\Filesystem;

class SqlReader
{
    /** Query map item - comment */
    const COMMENT = 0;

    /** Query map item - quoted value */
    const QUOTED = 1;

    /** Map of quote chars */
    const QUOTE_MAP = ['"' => 0, '\'' => 1, '`' => 2];

    /** Map of whitespace chars */
    const WHITESPACE_MAP = [' ' => 0, "\n" => 1, "\r" => 2, "\t" => 3, "\h" => 4];
    
    /** Comment syntaxes */
    const COMMENT_SYNTAXES = [
        ['#', "\n"],
        ['-- ', "\n"],
        ['/*', '*/'],
    ];

    /** @var string */
    private $input;

    /** @var string */
    private $delimiter = ';';

    function __construct(string $input)
    {
        $this->input = $input;
    }

    /**
     * Create from a file
     */
    static function fromFile(string $filepath): self
    {
        Filesystem::ensureFileExists($filepath);

        return new self(file_get_contents($filepath));
    }

    /**
     * Get delimiter
     *
     * @return string
     */
    function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Set delimiter
     *
     * @param string $delimiter single character
     * @return $this
     */
    function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Read the SQL
     *
     * The queryMap argument callback argument is an array with the following structure:
     *
     *      array(
     *          array(SqlReader::COMMENT or QUOTED, start offset, end offset),
     *          ...
     *      )
     *
     * @param callable|null $callback callback(string query, array queryMap): void to invoke for each query
     * @return string[]|int array or number of queries (if callback is used)
     */
    function read(?callable $callback = null)
    {
        $query = null;
        $queries = $callback === null ? [] : 0;
        $queryMap = [];
        $queryOffset = 0;

        $inQuotes = false;
        $quoteChar = null;
        $quoteFound = false;
        $escaped = false;

        $inComment = false;
        $commentMatchesInitial = array_fill_keys(array_keys(self::COMMENT_SYNTAXES), 0);
        $commentMatches = $commentMatchesInitial;
        $commentEndSyntax = null;
        $commentEndMatch = 0;

        $segmentOffset = 0; // start offset of a quote/comment

        $handleCompleteQuery = function () use (&$query, &$queries, &$queryMap, $callback) {
            if ($query !== null) {
                if ($callback !== null) {
                    call_user_func($callback, $query, $queryMap);
                    ++$queries;
                } else {
                    $queries[] = $query;
                }

                $query = null;
                $queryMap = [];
            }
        };

        $length = strlen($this->input);

        for ($i = 0; $i < $length; ++$i) {
            $char = $this->input[$i];

            // parse character
            if ($inQuotes) {
                // inside of a quoted string
                switch ($char) {
                    case '\\';
                        $escaped = !$escaped;
                        break;
                    case $quoteChar:
                        if ($quoteFound) {
                            $quoteFound = false; // repeated quote = escaped
                        } elseif (!$escaped) {
                            $quoteFound = true;
                        }

                        $escaped = false;
                        break;
                    default:
                        $escaped = false;

                        if ($quoteFound) {
                            $inQuotes = false;
                            $queryMap[] = [self::QUOTED, $segmentOffset - $queryOffset, $i - $queryOffset];
                        }
                        break;
                }
            } elseif ($inComment) {
                // inside of a comment
                if (
                    $commentEndSyntax === "\n" && ($char === "\n" || $char === "\r")
                    || $char === $commentEndSyntax[$commentEndMatch]
                ) {
                    if (!isset($commentEndSyntax[++$commentEndMatch])) {
                        $inComment = false;
                        $queryMap[] = [self::COMMENT, $segmentOffset - $queryOffset, $i - $queryOffset];
                    }
                } else {
                    $commentEndMatch = 0;
                }
            }

            if (!$inQuotes && !$inComment) {
                // detect comments
                if ($commentMatches !== null) {
                    for ($j = 0; isset($commentMatches[$j]); ++$j) {
                        if ($char === self::COMMENT_SYNTAXES[$j][0][$commentMatches[$j]]) {
                            if (!isset(self::COMMENT_SYNTAXES[$j][0][++$commentMatches[$j]])) {
                                $inComment = true;
                                $segmentOffset = $i;
                                $commentEndSyntax = self::COMMENT_SYNTAXES[$j][1];
                                $commentEndMatch = 0;
                                $commentMatches = null;
                            }
                        } else {
                            $commentMatches[$j] = 0;
                        }
                    }
                } else {
                    // a comment has just ended, just reset the matches
                    $commentMatches = $commentMatchesInitial;
                }

                // detect quoted strings / delimiter
                if (!$inComment) {
                    if (isset(self::QUOTE_MAP[$char])) {
                        // start of a quoted string
                        $inQuotes = true;
                        $segmentOffset = $i;
                        $quoteChar = $char;
                        $quoteFound = false;
                        $escaped = false;
                    } elseif ($char === $this->delimiter) {
                        // delimiter
                        $handleCompleteQuery();
                        continue;
                    }
                }
            }

            // append character to the current query
            if ($query === null) {
                if (!isset(self::WHITESPACE_MAP[$char])) {
                    // first non-whitespace character encountered
                    $query = $char;
                    $queryOffset = $i;
                }
            } else {
                $query .= $char;
            }
        }

        $handleCompleteQuery();

        return $queries;
    }
}
