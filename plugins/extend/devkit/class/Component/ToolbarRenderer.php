<?php

namespace SunlightExtend\Devkit\Component;

use Kuria\Debug\Dumper;
use Sunlight\CallbackHandler;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;
use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Plugin\Plugin;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Cookie;

class ToolbarRenderer
{
    /** @var array */
    private $sqlLog;
    /** @var array */
    private $eventLog;
    /** @var \SplObjectStorage */
    private $missingLocalizations;
    /** @var array[] */
    private $dumps;

    /**
     * @param array[] $dumps
     */
    function __construct(
        array $sqlLog,
        array $eventLog,
        \SplObjectStorage $missingLocalizations,
        array $dumps
    ) {
        $this->sqlLog = $sqlLog;
        $this->eventLog = $eventLog;
        $this->missingLocalizations = $missingLocalizations;
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
                $this->renderInfo();
                $this->renderTime($now);
                $this->renderMemory();
                $this->renderDatabase();
                $this->renderEvents();
                $this->renderPluginErrors();
                $this->renderLang();
                $this->renderDumps();

                Extend::call('devkit.toolbar.render');

                $this->renderRequest();
                $this->renderLogin();

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
    private function renderInfo(): void
    {
        ?>
<div class="devkit-section devkit-sl">
    <?= Core::VERSION ?>
    <?= Core::DIST ?>
</div>
<?php
    }

    /**
     * Render the time section
     */
    private function renderTime(float $now): void
    {
        ?>
<div class="devkit-section devkit-time">
    <?= round(($now - Core::$start) * 1000) ?>ms
</div>
<?php
    }

    /**
     * Render the memory section
     */
    private function renderMemory(): void
    {
        ?>
<div class="devkit-section devkit-memory">
    <?= number_format(round(memory_get_peak_usage() / 1048576, 1), 1) ?>MB
</div>
<?php
    }

    /**
     * Render the database section
     */
    private function renderDatabase(): void
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
                        <td>
                            <a href="#" class="devkit-hideshow" data-target="#devkit-db-trace-<?= $index ?>">show</a>
                        </td>
                        <td class="break-all"><code><?= _e($entry['query']) ?></code></td>
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

    /**
     * Render the event section
     */
    private function renderEvents(): void
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
    <?= count($this->eventLog) ?>
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
                <?php foreach ($this->eventLog as $event => $data): ?>
                    <tr>
                        <td><?= _e($event) ?></td>
                        <td><?= _e($data[0]) ?></td>
                        <td><?php $this->renderEventArgs($data[1]) ?></td>
                    </tr>
                <?php endforeach ?>
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

    private function renderCallback($callback): string
    {
        if ($callback instanceof \Closure) {
            return $this->renderClosure($callback);
        }

        if (is_callable($callback, true, $callableName)) {
            return $callableName;
        }

        return sprintf('INVALID? %s', Dumper::dump($callback));
    }

    private function renderClosure(\Closure $closure): string
    {
        $refl = new \ReflectionFunction($closure);

        // try to render system closure used vars if on PHP 8.1+
        if (PHP_VERSION_ID >= 80100 && $this->isSystemClosure($refl)) {
            $vars = $refl->getClosureUsedVariables();

            if (count($vars) === 1 && isset($vars['definition'])) {
                // HCM module callback definition from an extend plugin
                $vars = $vars['definition'];
            }

            $vars = array_reduce(
                array_keys($vars),
                function ($output, $var) use ($vars) {
                    if (is_scalar($vars[$var])) {
                        return ($output !== '' ? $output . ', ' : '')
                            . $var
                            . '='
                            . Dumper::dump($vars[$var], 1, 128);
                    }

                    return $output;
                },
                ''
            );

            if ($vars !== '') {
                return sprintf('Closure(%s)', $vars);
            }
        }

        // try to render info about the owning plugin
        if (($closureThis = $refl->getClosureThis()) instanceof Plugin) {
            return sprintf('Closure(plugin="%s")', $closureThis->getId());
        }

        // fallback to rendering file and line
        return sprintf('Closure(file="%s", line=%d)', $refl->getFileName(), $refl->getStartLine());
    }

    private function isSystemClosure(\ReflectionFunction $closureRefl): bool
    {
        static $systemClosurePathMap;

        if ($systemClosurePathMap === null) {
            $systemClosurePathMap = [
                (new \ReflectionClass(CallbackHandler::class))->getFileName() => true,
                (new \ReflectionClass(ExtendPlugin::class))->getFileName() => true,
            ];
        }

        return isset($systemClosurePathMap[$closureRefl->getFileName()]);
    }

    /**
     * Render the  plugin errors section
     */
    private function renderPluginErrors(): void
    {
        $pluginErrors = [];

        foreach (Core::$pluginManager->getPlugins()->inactiveMap as $inactivePlugin) {
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

    /**
     * Render the localization section
     */
    private function renderLang(): void
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
        <div class="devkit-heading">Missing localizations for language <em><?= _e(Core::$lang) ?></em> (<?= $totalMissingLocalizations ?>)</div>

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
                        <td><?= _e($row['count']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
    <?php
    }

    private function renderDumps(): void
    {
        ?>
<div class="devkit-section devkit-dump devkit-toggleable">
    <?= count($this->dumps) ?>
</div>

<div class="devkit-content">
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
    <?php
    }

    /**
     * Render event argument list
     */
    private function renderEventArgs(array $args): void
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
    private function renderRequest(): void
    {
        ?>
<div class="devkit-section devkit-request devkit-toggleable">
    <?= _e($_SERVER['REQUEST_METHOD']) ?>
</div>

<div class="devkit-content">
    <div>
        <?php foreach (['_GET', '_POST', '_COOKIE', '_SESSION'] as $globalVarName): ?>
            <?php if (!empty($GLOBALS[$globalVarName])): ?>
            <div class="devkit-heading devkit-hideshow">
                $<?= $globalVarName, ' (', count($GLOBALS[$globalVarName]), ')' ?>
            </div>

            <div class="devkit-request-dump devkit-hideshow-target"><pre><?= _e(Dumper::dump($GLOBALS[$globalVarName])) ?></pre></div>
            <?php endif ?>
        <?php endforeach ?>
    </div>
</div>
<?php
    }

    /**
     * Render the login section
     */
    private function renderLogin(): void
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
}
