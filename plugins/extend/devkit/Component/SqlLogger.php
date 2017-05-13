<?php

namespace SunlightExtend\Devkit\Component;

use Kuria\Error\Screen\WebErrorScreen;

/**
 * Devkit SQL logger
 *
 * @author ShiraNai7 <shira.cz>
 */
class SqlLogger
{
    /** @var array[] */
    private $log = array();
    /** @var float|null */
    private $timerStartedAt = 0;

    /**
     * Set the timer
     */
    public function setTimer()
    {
        $this->timerStartedAt = microtime(true);
    }

    /**
     * Log SQL query
     *
     * @param string $query
     */
    public function log($query)
    {
        $dummyException = new \Exception();

        $this->log[] = array(
            'query' => $query,
            'time' => microtime(true) - $this->timerStartedAt,
            'trace' => $dummyException->getTraceAsString(),
        );

        $this->timerStartedAt = 0;
    }

    /**
     * Get log
     *
     * @return array
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Render SQL log in debugger screen
     *
     * @param object $exception
     * @param bool   $debug
     * @param object $handler
     */
    public function showInDebugScreen($exception, $debug, $handler)
    {
        if ($debug && $handler instanceof WebErrorScreen) {
            $that = $this;

            $handler->on('render.debug', function (array $view) use ($that) {
                $log = $that->getLog();
                $logSize = sizeof($log);

                $logHtml = _buffer(function () use ($log) {
                    ?>
<ol>
    <?php foreach ($log as $entry): ?>
    <li><code><?php echo _e($entry['query']) ?></code></li>
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
            });
        }
    }
}
