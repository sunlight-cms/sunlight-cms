<?php

namespace Sunlight\Database;

use Sunlight\Util\Filesystem;

class SqlReader
{
    /** Query map item - comment */
    const COMMENT = 0;
    /** Query map item - quoted value */
    const QUOTED = 1;

    /** @var string */
    protected $input;
    /** @var string */
    protected $delimiter = ';';
    /** @var array */
    protected $quoteMap = ['"' => 0, '\'' => 1, '`' => 2];
    /** @var array */
    protected $whitespaceMap = [' ' => 0, "\n" => 1, "\r" => 2, "\t" => 3, "\h" => 4];
    /** @var array */
    protected $commentSyntaxMap = [
        ['#', "\n"],
        ['-- ', "\n"],
        ['/*', '*/'],
    ];

    /**
     * @param string $input
     */
    function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * Create from a file
     *
     * @param string $filepath
     * @return static
     */
    static function fromFile($filepath)
    {
        Filesystem::ensureFileExists($filepath);

        return new static(file_get_contents($filepath));
    }

    /**
     * Get delimiter
     *
     * @return string
     */
    function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * Set delimiter
     *
     * @param string $delimiter single character
     * @return SqlReader
     */
    function setDelimiter($delimiter)
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
    function read($callback = null)
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
        $commentMatchesInitial = array_fill_keys(array_keys($this->commentSyntaxMap), 0);
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
                            $queryMap[] = [static::QUOTED, $segmentOffset - $queryOffset, $i - $queryOffset];
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
                        $queryMap[] = [static::COMMENT, $segmentOffset - $queryOffset, $i - $queryOffset];
                    }
                } else {
                    $commentEndMatch = 0;
                }
            }

            if (!$inQuotes && !$inComment) {
                // detect comments
                if ($commentMatches !== null) {
                    for ($j = 0; isset($commentMatches[$j]); ++$j) {
                        if ($char === $this->commentSyntaxMap[$j][0][$commentMatches[$j]]) {
                            if (!isset($this->commentSyntaxMap[$j][0][++$commentMatches[$j]])) {
                                $inComment = true;
                                $segmentOffset = $i;
                                $commentEndSyntax = $this->commentSyntaxMap[$j][1];
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
                    if (isset($this->quoteMap[$char])) {
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
                if (!isset($this->whitespaceMap[$char])) {
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
