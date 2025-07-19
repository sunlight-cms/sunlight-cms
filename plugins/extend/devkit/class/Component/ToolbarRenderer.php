<?php

namespace SunlightExtend\Devkit\Component;

use Kuria\Debug\Dumper;
use Kuria\RequestInfo\RequestInfo;
use Sunlight\Callback\ScriptCallback;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;
use Sunlight\Log\LogEntry;
use Sunlight\Logger;
use Sunlight\Plugin\PluginHcmHandler;
use Sunlight\Plugin\Plugin;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Cookie;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;

class ToolbarRenderer
{
    /** @var array */
    private $sqlLog;
    /** @var array */
    private $eventLog;
    /** @var int */
    private $eventCount;
    /** @var \SplObjectStorage */
    private $missingLocalizations;
    /** @var LogEntry[] */
    private $logEntries;
    /** @var array[] */
    private $dumps;

    /**
     * @param LogEntry[] $logEntries
     * @param array[] $dumps
     */
    function __construct(
        array $sqlLog,
        array $eventLog,
        int $eventCount,
        \SplObjectStorage $missingLocalizations,
        array $logEntries,
        array $dumps
    ) {
        $this->sqlLog = $sqlLog;
        $this->eventLog = $eventLog;
        $this->eventCount = $eventCount;
        $this->missingLocalizations = $missingLocalizations;
        $this->logEntries = $logEntries;
        $this->dumps = $dumps;
    }

    /**
     * Render the toolbar
     */
    function render(): string
    {
        return _buffer(function () {
            $now = microtime(true);

            // determine class
            if (Cookie::get('sl_devkit_toolbar') === 'closed') {
                $class = 'devkit-toolbar-closed';
            } else {
                $class = 'devkit-toolbar-open';
            }

            // start
            ?>
            <div id="devkit-toolbar" class="<?= $class ?>">
                <?php

                // sections
                $this->renderInfoSection();
                $this->renderTimeSection($now);
                $this->renderMemorySection();
                $this->renderDatabaseSection();
                $this->renderEventsSection();
                $this->renderPluginErrorsSection();
                $this->renderLoggerSection();
                $this->renderLangSection();
                $this->renderDumpsSection();

                Extend::call('devkit.toolbar.render');

                $this->renderRequestSection();
                $this->renderLoginSection();

                // controls
                ?>
                <div class="devkit-button devkit-close">×</div>
                <div class="devkit-button devkit-open">+</div>
            </div>
<?php
        });
    }

    private function renderInfoSection(): void
    {
        ?>
<div class="devkit-section devkit-sl">
    <?= _e(Core::VERSION) ?>
</div>
<?php
    }

    private function renderTimeSection(float $now): void
    {
        ?>
<div class="devkit-section devkit-time">
    <?= round(($now - Core::$start) * 1000) ?>ms
</div>
<?php
    }

    private function renderMemorySection(): void
    {
        ?>
<div class="devkit-section devkit-memory">
    <?= _e($this->formatNumber(round(memory_get_peak_usage() / 1048576, 1), 1)) ?>MB
</div>
<?php
    }

    private function renderDatabaseSection(): void
    {
        ?>
<div class="devkit-section devkit-database devkit-toggleable">
    <?= count($this->sqlLog) ?>
</div>

<div class="devkit-content">
    <div>
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
                <?php foreach ($this->sqlLog as $index => $entry): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= round($entry['time'] * 1000) ?>ms</td>
                        <td><a href="#" class="devkit-hideshow" data-target="#devkit-db-trace-<?= $index ?>">show</a></td>
                        <td class="devkit-break-all"><code><?= _e($entry['query']) ?></code></td>
                    </tr>
                    <tr id="devkit-db-trace-<?= $index ?>" class="devkit-hidden">
                        <td colspan="4">
                            <pre><?= _e($entry['trace']) ?></pre>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php
    }

    private function renderEventsSection(): void
    {
        $listeners = Core::$eventEmitter->getListeners();
        $eventListenerRows = [];

        ksort($listeners, defined('SORT_NATURAL') ? SORT_NATURAL : SORT_STRING);

        foreach ($listeners as $event => $eventListeners) {
            foreach ($eventListeners as $eventListener) {
                $eventListenerRows[] = [
                    $event,
                    $this->renderCallback($eventListener->callback),
                    $eventListener->priority,
                ];
            }
        }

        ?>
<div class="devkit-section devkit-extend devkit-toggleable">
    <?= _e($this->formatNumber($this->eventCount)) ?>
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Extend event log</div>

        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Count</th>
                    <th>Calls</th>
                    <th>Args</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $eventIndex = 0;

                foreach ($this->eventLog as $event => $data) {
                ?>
                    <tr>
                        <td><?= _e($event) ?></td>
                        <td><?= _e(count($data[1])) ?></td>
                        <td><a href="#" class="devkit-hideshow" data-target="#devkit-event-calls-<?= $eventIndex ?>">show</a></td>
                        <td><?php $this->renderEventArgs($data[0]) ?></td>
                    </tr>
                    <tr id="devkit-event-calls-<?= $eventIndex ?>" class="devkit-hidden">
                        <td colspan="4"><?php $this->renderEventCalls($data[1]) ?></td>
                    </tr>
                <?php
                    ++$eventIndex;
                }
                ?>
            </tbody>
        </table>

        <div class="devkit-heading">Event listeners</div>

        <table>
            <thead>
            <tr>
                <th>Event</th>
                <th>Callback</th>
                <th>Priority</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($eventListenerRows as $row): ?>
                <tr>
                    <td><?= _e($row[0]) ?></td>
                    <td><code><?= _e($row[1]) ?></code></td>
                    <td><code><?= _e($row[2]) ?></code></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php
    }

    private function renderPluginErrorsSection(): void
    {
        $pluginErrors = [];

        foreach (Core::$pluginManager->getPlugins()->inactiveMap as $inactivePlugin) {
            if ($inactivePlugin->hasStatus(Plugin::STATUS_DISABLED)) {
                continue;
            }

            foreach ($inactivePlugin->getErrors() as $error) {
                $pluginErrors[$inactivePlugin->getId()][] = $error;
            }
        }

        if (empty($pluginErrors)) {
            return;
        }

        $pluginErrorCount = count($pluginErrors);

        ?>
<div class="devkit-section devkit-section-error devkit-plugin-errors devkit-toggleable">
    <?= $pluginErrorCount ?> plugin <?= $pluginErrorCount > 1 ? 'errors' : 'error' ?>
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Plugin errors</div>

        <ul>
            <?php foreach ($pluginErrors as $pluginIdentifier => $errors): ?>
                <li><?= _e($pluginIdentifier) ?>
                    <ol>
                        <?php foreach ($errors as $error): ?>
                            <li><?= _e($error) ?></li>
                        <?php endforeach ?>
                    </ol>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
<?php
    }

    private function renderLoggerSection(): void
    {
        $class = '';

        if (!empty($this->logEntries)) {
            $minLevel = min(array_column($this->logEntries, 'level'));

            if ($minLevel <= Logger::ERROR) {
                $class = ' devkit-section-error';
            } elseif ($minLevel <= Logger::WARNING) {
                $class = ' devkit-section-warning';
            }
        }

        ?>
<div class="devkit-section devkit-logger devkit-toggleable<?= $class ?>">
    <?= count($this->logEntries) ?>
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Log entries for current request</div>

        <table>
            <thead>
                <tr>
                    <th>Level</th>
                    <th>Category</th>
                    <th>Message</th>
                    <th>Context</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($this->logEntries)): ?>
                    <?php
                    foreach ($this->logEntries as $index => $entry):
                        $parsedContext = $entry->context !== null ? json_decode($entry->context) : null;
                    ?>
                        <tr>
                            <td class="devkit-cell-shrink"><?= _e(Logger::LEVEL_NAMES[$entry->level]) ?></td>
                            <td class="devkit-cell-shrink"><?= _e($entry->category) ?></td>
                            <td><code><?= _e(StringHelper::ellipsis($entry->message, 1024, false)) ?></code></td>
                            <td class="devkit-cell-shrink">
                                <?php if ($parsedContext !== null): ?>
                                    <a href="#" class="devkit-hideshow" data-target="#devkit-log-context-<?= $index ?>">show</a>
                                <?php else: ?>
                                    -
                                <?php endif ?>
                            </td>
                            <td class="devkit-cell-shrink">
                                <?php if ($entry->id !== null): ?>
                                    <a href="<?= _e(Router::admin('log-detail', ['query' => ['id' => $entry->id]])) ?>" target="_blank">detail</a>
                                <?php else: ?>
                                    -
                                <?php endif ?>
                            </td>
                        </tr>
                        <?php if ($parsedContext !== null): ?>
                            <tr id="devkit-log-context-<?= $index ?>" class="devkit-hidden">
                                <td colspan="5">
                                    <table>
                                        <?php foreach ($parsedContext as $key => $value): ?>
                                            <tr>
                                                <th><code><?= _e($key) ?></code></th>
                                                <td><pre><?= _e(is_string($value) ? $value : Dumper::dump($value)) ?></pre></td>
                                            </tr>
                                        <?php endforeach ?>
                                    </table>
                                </td>
                            </tr>
                        <?php endif ?>
                    <?php endforeach ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">None</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?php
    }

    private function renderLangSection(): void
    {
        $missingLocalizationRows = [];

        foreach ($this->missingLocalizations as $dict) {
            foreach ($this->missingLocalizations[$dict] as $missingKey => $missingKeyCount) {
                if (Core::$dictionary === $dict) {
                    $dictDescription = '{main}';
                } elseif ($dict instanceof LocalizationDirectory) {
                    $dictPath = $dict->getPathForLanguage(Core::$lang);
                    $dictDescription = $dictPath;

                    if (!is_file($dictPath)) {
                        $dictDescription .= ' [does not exist]';
                    }
                } else {
                    $dictDescription = Dumper::dump($dict, 1);
                }

                $missingLocalizationRows[] = [
                    'dict' => $dictDescription,
                    'key' => $missingKey,
                    'count' => $missingKeyCount,
                ];
            }
        }

        $totalMissingLocalizations = count($missingLocalizationRows);

        if ($totalMissingLocalizations === 0) {
            return;
        }

        ?>
<div class="devkit-section devkit-section-error devkit-lang devkit-toggleable">
    <?= $totalMissingLocalizations ?> missing
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Missing localizations for language <?= _e(Core::$lang) ?></div>

        <table>
            <thead>
                <tr>
                    <th>Dictionary</th>
                    <th>Key</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($missingLocalizationRows as $row): ?>
                    <tr>
                        <td><code><?= _e($row['dict']) ?></code></td>
                        <td><?= _e($row['key']) ?></td>
                        <td><?= _num($row['count']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
    <?php
    }

    private function renderDumpsSection(): void
    {
        $count = count($this->dumps);

        ?>
<div class="devkit-section devkit-dump devkit-toggleable<?php if ($count > 0): ?> devkit-section-warning<?php endif ?>">
    <?= $count ?>
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Dumped values</div>

        <table>
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($this->dumps)): ?>
                    <?php foreach ($this->dumps as $dump): ?>
                        <tr>
                            <td title="<?= _e($dump['file']) ?>"><?= _e(basename($dump['file'])), ':', $dump['line'] ?></td>
                            <td><pre><?= _e($dump['dump']) ?></pre></td>
                        </tr>
                    <?php endforeach ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">None</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>
    <?php
    }

    private function renderRequestSection(): void
    {
        $valueRenderer = static function ($value) {
            return '<pre>' . _e(Dumper::dump($value)) . '</pre>';
        };

        ?>
<div class="devkit-section devkit-request devkit-toggleable">
    <?= _e(Request::method()) ?>
</div>

<div class="devkit-content">
    <div>
        <?php foreach (['_GET', '_POST', '_COOKIE', '_SESSION'] as $globalVarName): ?>
            <div class="devkit-heading devkit-hideshow">
                $<?= $globalVarName ?>
            </div>

            <div class="devkit-hideshow-target"><?php $this->renderArray($GLOBALS[$globalVarName], $valueRenderer) ?></div>
        <?php endforeach ?>

        <div class="devkit-heading devkit-hideshow">
            HTTP headers
        </div>

        <div class="devkit-hideshow-target"><?php $this->renderArray(RequestInfo::getHeaders(), $valueRenderer) ?></div>
    </div>
</div>
<?php
    }

    private function renderLoginSection(): void
    {
        if (User::isLoggedIn()) {
            $loginInfo = sprintf('level %d', User::getLevel());
            $loginName = User::getUsername();
        } else {
            $loginInfo = 'not logged in';
            $loginName = '---';
        }

        ?>
<a href="<?= _e(Router::module('login')) ?>">
    <div class="devkit-section devkit-login" title="<?= $loginInfo ?>">
        <?= _e($loginName) ?>
    </div>
</a>
<?php
    }

    private function renderCallback($callback): string
    {
        if ($callback instanceof \Closure) {
            return $this->renderClosure($callback);
        }

        if ($callback instanceof ScriptCallback) {
            return $callback->path;
        }

        if ($callback instanceof PluginHcmHandler) {
            return $this->renderCallback($callback->callback);
        }

        if (is_callable($callback, true, $callableName)) {
            return $callableName;
        }

        return sprintf('INVALID? %s', Dumper::dump($callback));
    }

    private function renderClosure(\Closure $closure): string
    {
        $refl = new \ReflectionFunction($closure);

        // try to render info about the owning plugin
        if (($closureThis = $refl->getClosureThis()) instanceof Plugin) {
            return sprintf('Closure(plugin="%s")', $closureThis->getId());
        }

        // fallback to rendering file and line
        return sprintf('Closure(file="%s", line=%d)', $refl->getFileName(), $refl->getStartLine());
    }

    private function renderEventArgs(array $args): void
    {
        if (empty($args)) {
            echo '-';
            return;
        }

        $first = true;

        echo '<code>';

        foreach ($args as $argName => $argTypeMap) {
            if ($first) {
                $first = false;
            } else {
                echo ', ';
            }

            echo _e($argName),
                ' => ',
                '<span class="devkit-type">',
                count($argTypeMap) <= 3 ? _e(implode('|', array_keys($argTypeMap))) : 'mixed',
                '</span>';
        }

        echo '</code>';
    }

    private function renderEventCalls(array $calls): void
    {
        $argRenderer = static function ($dump) {
            return '<pre>' . _e($dump) . '</pre>';
        };

        ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Args</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($calls as $index => $args): ?>
            <tr>
                <td><?= _e($index) ?></td>
                <td>
                    <?php $this->renderArray($args, $argRenderer) ?>
                </td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php
    }

    private function renderArray(array $array, \Closure $valueRenderer): void
    {
        ?>
<table>
    <?php if (!empty($array)): ?>
        <?php foreach ($array as $key => $value): ?>
            <tr>
                <th><code><?= _e($key) ?></code></th>
                <td><?= $valueRenderer($value) ?></td>
            </tr>
        <?php endforeach ?>
    <?php else: ?>
        <tr><td>Empty</td></tr>
    <?php endif ?>
</table>
<?php
    }

    private function formatNumber($number, int $decimals = 0): string
    {
        return number_format($number, $decimals, '.', '');
    }
}
