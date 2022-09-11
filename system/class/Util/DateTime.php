<?php

namespace Sunlight\Util;

abstract class DateTime
{
    /**
     * Format date-time for HTTP
     *
     * @param int $time timestamp
     * @param bool $relative relativne to current time
     */
    static function formatForHttp(int $time, bool $relative = false): string
    {
        if ($relative) {
            $time += time();
        }

        return gmdate('D, d M Y H:i:s', $time) . ' GMT';
    }
}
