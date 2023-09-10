<?php

namespace Sunlight\Log;

use Sunlight\Database\Database as DB;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\StringHelper;

class DatabaseHandler implements LogHandlerInterface
{
    function log(LogEntry $entry): void
    {
        $entry->id = StringGenerator::generateUuidV4();

        DB::insert('log', [
            'id' => $entry->id,
            'level' => $entry->level,
            'category' => StringHelper::cut($entry->category, 64),
            'time' => $entry->time,
            'message' => StringHelper::cut($entry->message, 32768),
            'method' => $entry->method !== null ? StringHelper::cut($entry->method, 32) : null,
            'url' => $entry->url !== null ? StringHelper::cut($entry->url, 2048) : null,
            'ip' => $entry->ip !== null ? StringHelper::cut($entry->ip, 45) : null,
            'user_agent' => $entry->userAgent !== null ? StringHelper::cut($entry->userAgent, 255) : null,
            'user_id' => $entry->userId,
            'context' => $entry->context !== null && strlen($entry->context) <= DB::MAX_TEXT_LENGTH ? $entry->context : null,
        ]);
    }

    function get($id): ?LogEntry
    {
        $row = DB::queryRow('SELECT * FROM ' . DB::table('log') . ' WHERE id=' . DB::val($id));

        if ($row === false) {
            return null;
        }

        return $this->entryFromRow($row);
    }

    function search(LogQuery $query): array
    {
        $result = DB::query(
            'SELECT * FROM ' . DB::table('log')
            . ' WHERE ' . $this->getRowFilter($query)
            . ' ORDER BY time ' . ($query->desc ? 'DESC' : 'ASC')
            . ' LIMIT ' . DB::val($query->offset) . ', ' . DB::val($query->limit)
        );

        $entries = [];

        while ($row = DB::row($result)) {
            $entries[] = $this->entryFromRow($row);
        }

        return $entries;
    }

    function getTotalResults(LogQuery $query): int
    {
        return DB::count('log', $this->getRowFilter($query));
    }

    function getCategories(): array
    {
        return DB::queryRows('SELECT DISTINCT category FROM ' . DB::table('log'), null, 'category');
    }

    function cleanup(int $thresholdTime): void
    {
        DB::delete('log', 'time<' . DB::val($thresholdTime));
    }

    private function getRowFilter(LogQuery $query): string
    {
        $conds = [];

        if ($query->maxLevel !== null) {
            $conds[] = 'level<=' . DB::val($query->maxLevel);
        }
        if ($query->category !== null) {
            $conds[] = 'category=' . DB::val($query->category);
        }
        if ($query->since !== null) {
            $conds[] = 'time>=' . DB::val($query->since);
        }
        if ($query->until !== null) {
            $conds[] = 'time<=' . DB::val($query->until);
        }
        if ($query->keyword !== null) {
            $conds[] = 'message LIKE ' . DB::val('%' . $query->keyword . '%');
        }
        if ($query->method !== null) {
            $conds[] = 'method=' . DB::val($query->method);
        }
        if ($query->urlKeyword !== null) {
            $conds[] = 'url LIKE ' . DB::val('%' . $query->urlKeyword . '%');
        }
        if ($query->ip !== null) {
            $conds[] = 'ip=' . DB::val($query->ip);
        }
        if ($query->userId !== null) {
            $conds[] = 'user_id=' . DB::val($query->userId);
        }

        return empty($conds) ? '1' : implode(' AND ', $conds);
    }

    private function entryFromRow(array $row): LogEntry
    {
        $entry = new LogEntry();
        $entry->id = $row['id'];
        $entry->level = (int) $row['level'];
        $entry->category = $row['category'];
        $entry->time = (int) $row['time'];
        $entry->message = $row['message'];
        $entry->method = $row['method'];
        $entry->url = $row['url'];
        $entry->ip = $row['ip'];
        $entry->userAgent = $row['user_agent'];
        $entry->userId = $row['user_id'] !== null ? (int) $row['user_id'] : null;
        $entry->context = $row['context'];

        return $entry;
    }
}
