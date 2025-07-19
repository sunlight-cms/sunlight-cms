<?php

namespace SunlightExtend\Devkit\Component;

class SqlLogger
{
    /** @var array[] */
    private $log = [];
    /** @var float|null */
    private $timerStartedAt = 0;

    function setTimer(): void
    {
        $this->timerStartedAt = microtime(true);
    }

    function log(string $query): void
    {
        $dummyException = new \Exception();

        $this->log[] = [
            'query' => $query,
            'time' => microtime(true) - $this->timerStartedAt,
            'trace' => $dummyException->getTraceAsString(),
        ];

        $this->timerStartedAt = 0;
    }

    function getLog(): array
    {
        return $this->log;
    }
}
