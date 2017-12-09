<?php

namespace SunlightExtend\Devkit\Component;

use Kuria\Debug\Dumper;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;
use Sunlight\Plugin\InactivePlugin;
use SunlightExtend\Devkit\DevkitPlugin;

/**
 * Devkit toolbar renderer
 *
 * @author ShiraNai7 <shira.cz>
 */
class ToolbarRenderer
{
    /** @var array */
    private $sqlLog;
    /** @var array */
    private $eventLog;
    /** @var \SplObjectStorage */
    private $missingLocalizations;

    /**
     * @param array             $sqlLog
     * @param array             $eventLog
     * @param \SplObjectStorage $missingLocalizations
     */
    public function __construct(array $sqlLog, array $eventLog, \SplObjectStorage $missingLocalizations)
    {
        $this->sqlLog = $sqlLog;
        $this->eventLog = $eventLog;
        $this->missingLocalizations = $missingLocalizations;
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
            if (isset($_COOKIE['sl_devkit_toolbar']) && $_COOKIE['sl_devkit_toolbar'] === 'closed') {
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
                $that->renderPluginErrors();
                $that->renderLang();

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
    <?php echo Core::VERSION ?>
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
        ?>
<div class="devkit-section devkit-database devkit-toggleable">
    <?php echo sizeof($this->sqlLog) ?>
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
                        <td><?php echo $index + 1 ?></td>
                        <td><?php echo round($entry['time'] * 1000) ?>ms</td>
                        <td>
                            <a href="#" class="devkit-hideshow" data-target="#devkit-db-trace-<?php echo $index ?>">show</a>
                        </td>
                        <td class="break-all"><code><?php echo _e($entry['query']) ?></code></td>
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
</div>
<?php
    }

    /**
     * Render the event section
     */
    public function renderEvents()
    {
        $listeners = Core::$eventEmitter->getListeners();
        $eventListenerRows = array();

        ksort($listeners, defined('SORT_NATURAL') ? SORT_NATURAL : SORT_STRING);

        foreach ($listeners as $event => $eventListeners) {
            foreach ($eventListeners as $eventListener) {
                $eventListenerRows[] = array($event, Dumper::dump($eventListener));
            }
        }

        ?>
<div class="devkit-section devkit-extend devkit-toggleable">
    <?php echo sizeof($this->eventLog) ?>
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
                        <td><?php echo _e($event) ?></td>
                        <td><?php echo $data[0] ?></td>
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
                <th>Listener</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($eventListenerRows as $row): ?>
                <tr>
                    <td><?php echo _e($row[0]) ?></td>
                    <td><code><?php echo _e($row[1]) ?></code></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php
    }

    /**
     * Render the  plugin errors section
     */
    public function renderPluginErrors()
    {
        $pluginErrors = array();

        foreach (Core::$pluginManager->allInactive() as $type => $inactivePlugins) {
            foreach ($inactivePlugins as $name => $inactivePlugin) {
                /** @var InactivePlugin $inactivePlugin */

                foreach ($inactivePlugin->getErrors() as $error) {
                    $pluginErrors["{$type}/{$name} ({$inactivePlugin->getFile()})"][] = $error;
                }

                foreach ($inactivePlugin->getConfigurationErrors() as $path => $error) {
                    $pluginErrors["{$type}/{$name} ({$inactivePlugin->getFile()})"][] = "[at {$path}] $error";
                }
            }
        }

        if (empty($pluginErrors)) {
            return;
        }

        $pluginErrorCount = sizeof($pluginErrors);

        ?>
<div class="devkit-section devkit-plugin-errors devkit-toggleable">
    <?php echo $pluginErrorCount ?> plugin <?php echo $pluginErrorCount > 1 ? 'errors' : 'error' ?>
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Plugin errors</div>

        <ul>
            <?php foreach ($pluginErrors as $pluginIdentifier => $errors): ?>
                <li><?php echo _e($pluginIdentifier) ?>
                    <ol>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo _e($error) ?></li>
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
    public function renderLang()
    {
        $missingLocalizationRows = array();

        foreach ($this->missingLocalizations as $dict) {
            foreach ($this->missingLocalizations[$dict] as $missingKey => $missingKeyCount) {
                if (Core::$lang === $dict) {
                    $dictDescription = '{main}';
                } elseif ($dict instanceof LocalizationDirectory) {
                    $dictPath = $dict->getPathForLanguage(_language);
                    $dictDescription = $dictPath;

                    if (!is_file($dictPath)) {
                        $dictDescription .= ' [does not exist]';
                    }
                } else {
                    $dictDescription = Dumper::dump($dict, 1);
                }

                $missingLocalizationRows[] = array(
                    'dict' => $dictDescription,
                    'key' => $missingKey,
                    'count' => $missingKeyCount,
                );
            }
        }

        $totalMissingLocalizations = sizeof($missingLocalizationRows);

        ?>
<div class="devkit-section devkit-lang devkit-toggleable">
    <?php if ($totalMissingLocalizations > 0): ?><span class="devkit-blood-badge"><?php endif ?>
        <?php echo $totalMissingLocalizations ?>
    <?php if ($totalMissingLocalizations > 0): ?></span><?php endif ?>
</div>

<div class="devkit-content">
    <div>
        <div class="devkit-heading">Missing localizations for language <em><?php echo _e(_language) ?></em> (<?php echo $totalMissingLocalizations ?>)</div>

        <?php if ($totalMissingLocalizations > 0): ?>
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
                            <td><code><?php echo _e($row['dict']) ?></code></td>
                            <td><?php echo _e($row['key']) ?></td>
                            <td><?php echo _e($row['count']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>None</p>
        <?php endif ?>
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

            <div class="devkit-request-dump devkit-hideshow-target"><pre><?php echo Dumper::dump($GLOBALS[$globalVarName]) ?></pre></div>
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
