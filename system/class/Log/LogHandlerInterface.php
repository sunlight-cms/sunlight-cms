<?php

namespace Sunlight\Log;

interface LogHandlerInterface
{
    /**
     * Add an entry to the log
     *
     * This method must set {@see LogEntry::$id}.
     */
    function log(LogEntry $entry): void;

    /**
     * Attempt to retrieve a log entry by ID
     *
     * @param string|int $id
     */
    function get($id): ?LogEntry;

    /**
     * List entries by the given parameters
     *
     * @return LogEntry[]
     */
    function search(LogQuery $query): array;

    /**
     * Count entries matching the given parameters (ignoring offset and limit)
     */
    function getTotalResults(LogQuery $query): int;

    /**
     * Get distinct categories present in the log
     *
     * @return string[]
     */
    function getCategories(): array;

    /**
     * Remove entries older than the given time
     */
    function cleanup(int $thresholdTime): void;
}
