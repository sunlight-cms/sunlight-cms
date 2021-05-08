<?php

namespace SunlightExtend\Devkit\Component;

class SqlLogger
{
    /** @var array[] */
    private $log = [];
    /** @var float|null */
    private $timerStartedAt = 0;

    /**
     * Set the timer
     */
    function setTimer(): void
    {
        $this->timerStartedAt = microtime(true);
    }

    /**
     * Log SQL query
     *
     * @param string $query
     */
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

    /**
     * Get log
     *
     * @return array
     */
    function getLog(): array
    {
        return $this->log;
    }

    /**
     * Render SQL log in debugger screen
     *
     * @param array $view
     */
    function showInDebugScreen(array $view): void
    {
        $log = $this->getLog();
        $logSize = count($log);

        $logHtml = _buffer(function () use ($log) {
            ?>
            <ol>
                <?php foreach ($log as $entry): ?>
                    <li><code><?= _e($entry['query']) ?></code></li>
                <?php endforeach ?>
            </ol>
            <?php
        });

        $view['extras'] .= <<<HTML
<div class="group">
    <div class="section">
        <h2 class="toggle-control closed" onclick="Kuria.Error.WebErrorScreen.toggle('devkit-sql-log', this)">SQL log <em>({$logSize})</em></h2>
        <div id="devkit-sql-log" class="hidden">
            {$logHtml}
        </div>
    </div>
</div>
HTML;
    }
}
