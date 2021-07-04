<?php

namespace Sunlight\Util;

abstract class DateTime
{
    /**
     * Formatovat cas jako HTTP-date
     *
     * @param int  $time     timestamp
     * @param bool $relative relativne k aktualnimu casu 1/0
     * @return string
     */
    static function formatForHttp(int $time, bool $relative = false): string
    {
        if ($relative) {
            $time += time();
        }

        return gmdate('D, d M Y H:i:s', $time) . ' GMT';
    }
}
