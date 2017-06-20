<?php

namespace SunlightExtend\Devkit\Component;

use Kuria\Debug\Dumper;
use Sunlight\Core;
use Sunlight\Extend;

/**
 * Devkit toolbar renderer
 *
 * @author ShiraNai7 <shira.cz>
 */
class ToolbarRenderer
{
    /** @var SqlLogger */
    private $sqlLogger;
    /** @var EventLogger */
    private $eventLogger;

    /**
     * @param SqlLogger   $sqlLogger
     * @param EventLogger $eventLogger
     */
    public function __construct(SqlLogger $sqlLogger, EventLogger $eventLogger)
    {
        $this->sqlLogger = $sqlLogger;
        $this->eventLogger = $eventLogger;
    }
    /**
     * Render the toolbar
     *
     * @return string
     */
    public function render()
    {
        $that = $this;

        return _buffer(function () use ($that) {
            $now = microtime(true);

            // determine class
            if (isset($_COOKIE['sl_devkit_toolbar']) && 'closed' === $_COOKIE['sl_devkit_toolbar']) {
                $class = 'devkit-toolbar-closed';
            } else {
                $class = 'devkit-toolbar-open';
            }

            // start
            ?>
            <div id="devkit-toolbar" class="<?php echo $class ?>">
                <?php

                // sections
                $that->renderInfo();
                $that->renderTime($now);
                $that->renderMemory();
                $that->renderDatabase();
                $that->renderEvents();

                Extend::call('devkit.toolbar.render');

                $that->renderRequest();
                $that->renderLogin();

                // controls
                ?>
                <div class="devkit-button devkit-close">×</div>
                <div class="devkit-button devkit-open">+</div>
            </div>
<?php
        });
    }

    /**
     * Render the system info section
     */
    public function renderInfo()
    {
        ?>
<div class="devkit-section devkit-info">
    <?php echo Core::VERSION, ' ', Core::DIST ?>
</div>
<?php
    }

    /**
     * Render the time section
     *
     * @param float $now
     */
    public function renderTime($now)
    {
        ?>
<div class="devkit-section devkit-time">
    <?php echo round(($now - Core::$start) * 1000) ?>ms
</div>
<?php
    }

    /**
     * Render the memory section
     */
    public function renderMemory()
    {
        ?>
<div class="devkit-section devkit-memory">
    <?php echo number_format(round(memory_get_peak_usage() / 1048576, 1), 1, '.', ',') ?>MB
</div>
<?php
    }

    /**
     * Render the database section
     */
    public function renderDatabase()
    {
        $sqlLog = $this->sqlLogger->getLog();

        ?>
<div class="devkit-section devkit-database devkit-toggleable">
    <?php echo sizeof($sqlLog) ?>
</div>

<div class="devkit-content">
    <div class="devkit-heading">SQL log</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Time</th>
                <th>Trace</th>
                <th>SQL</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sqlLog as $index => $entry): ?>
            <tr>
                <td><?php echo $index + 1 ?></td>
                <td><?php echo round($entry['time'] * 1000) ?>ms</td>
                <td>
                    <a href="#" class="devkit-hideshow" data-target="#devkit-db-trace-<?php echo $index ?>">show</a>
                </td>
                <td class="break-all"><?php echo _e($entry['query']) ?></td>
            </tr>
            <tr id="devkit-db-trace-<?php echo $index ?>" class="devkit-hidden">
                <td colspan="4">
                    <pre><?php echo _e($entry['trace']) ?></pre>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php
    }

    /**
     * Render the event section
     */
    public function renderEvents()
    {
        $events = $this->eventLogger->getLog();

        ?>
<div class="devkit-section devkit-extend devkit-toggleable">
    <?php echo sizeof($events) ?>
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Extend event log</div>
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Count</th>
                    <th>Args</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event => $data): ?>
                <tr>
                    <td><?php echo _e($event) ?></td>
                    <td><?php echo $data[0] ?></td>
                    <td><?php $this->renderEventArgs($data[1]) ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php
    }

    /**
     * Render event argument list
     *
     * @param array $args
     */
    public function renderEventArgs(array $args)
    {
        if (!empty($args)) {
            $eventArgIsFirst = true;
            foreach ($args as $eventArgName => $eventArgType) {
                if ($eventArgIsFirst) {
                    $eventArgIsFirst = false;
                } else {
                    echo ', ';
                }
                echo '<small>(' . _e($eventArgType) . ')</small> ' . _e($eventArgName);
            }
        } else {
            echo '-';
        }
    }

    /**
     * Render the request section
     */
    public function renderRequest()
    {
        ?>
<div class="devkit-section devkit-request devkit-toggleable">
    <?php echo _e($_SERVER['REQUEST_METHOD']) ?>
</div>

<div class="devkit-content">
    <div>
        <?php foreach (array('_GET', '_POST', '_COOKIE', '_SESSION') as $globalVarName): ?>
            <?php if (!empty($GLOBALS[$globalVarName])): ?>
            <div class="devkit-heading devkit-hideshow">
                $<?php echo $globalVarName, ' (', sizeof($GLOBALS[$globalVarName]), ')' ?>
            </div>

            <div class="devkit-request-dump devkit-hideshow-target"><?php echo Dumper::dump($GLOBALS[$globalVarName]) ?></div>
            <?php endif ?>
        <?php endforeach ?>
    </div>
</div>
<?php
    }

    /**
     * Render the login section
     */
    public function renderLogin()
    {
        if (_login) {
            $loginInfo = sprintf('level %d', _priv_level);
            $loginName = _loginname;
        } else {
            $loginInfo = 'not logged in';
            $loginName = '---';
        }

        ?>
<a href="<?php echo _linkModule('login') ?>">
    <div class="devkit-section devkit-login" title="<?php echo $loginInfo ?>">
        <?php echo _e($loginName) ?>
    </div>
</a>
<?php
    }
}
