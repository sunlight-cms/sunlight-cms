<?php

namespace SunlightExtend\Devkit;

use Kuria\Event\ObservableInterface;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Log\LogEntry;
use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Plugin\PluginData;
use Sunlight\Plugin\PluginManager;
use Sunlight\Settings;

class DevkitPlugin extends ExtendPlugin
{
    /** @var Component\SqlLogger */
    private $sqlLogger;
    /** @var Component\EventLogger */
    private $eventLogger;
    /** @var Component\MissingLocalizationLogger */
    private $missingLocalizationLogger;
    /** @var LogEntry[] */
    private $logEntries = [];
    /** @var array[] */
    private $dumps = [];

    function __construct(PluginData $data, PluginManager $manager)
    {
        parent::__construct($data, $manager);

        $this->sqlLogger = new Component\SqlLogger();
        $this->eventLogger = new Component\EventLogger();
        $this->missingLocalizationLogger = new Component\MissingLocalizationLogger();

        Extend::reg(ObservableInterface::ANY_EVENT, [$this->eventLogger, 'log'], 10000);
    }

    function addDump(string $file, int $line, string $dump): void
    {
        $this->dumps[] = [
            'file' => $file,
            'line' => $line,
            'dump' => $dump,
        ];
    }

    /**
     * Handle database query
     */
    function onDbQuery(): void
    {
        $this->sqlLogger->setTimer();
    }

    /**
     * Handle database query result
     */
    function onDbQueryPost(array $args): void
    {
        $this->sqlLogger->log($args['sql']);
    }

    /**
     * Handle a missing localization entry
     */
    function onMissingLocalization(array $args): void
    {
        $this->missingLocalizationLogger->log($args['dict'], $args['key']);
    }

    /**
     * Intercept and log emails
     */
    function onMail(array $args): void
    {
        if (!$this->getConfig()['mail_log_enabled']) {
            return;
        }

        $args['result'] = true;

        $time = GenericTemplates::renderTime(time());
        $headerString = '';

        foreach ($args['headers'] as $headerName => $headerValue) {
            $headerString .= sprintf("%s: %s\n", $headerName, $headerValue);
        }

        file_put_contents(SL_ROOT . 'mail.log', <<<ENTRY
Time: {$time}
Recipient: {$args['to']}
Subject: {$args['subject']}
{$headerString}
{$args['message']}

=====================================
=====================================

ENTRY
            , FILE_APPEND | LOCK_EX);
    }

    /**
     * Disable anti-spam in dev mode
     */
    function onIpLogCheck(array $args): void
    {
        if (!$this->getConfig()['disable_anti_spam']) {
            return;
        }

        if ($args['type'] == IpLog::ANTI_SPAM) {
            $args['result'] = true;
        }
    }

    function onLoggerFilter(array $args): void
    {
        // allow all messages through so they can be fully processed
        $args['should_log'] = true;
    }

    function onLoggerBefore(array $args): void
    {
        // restore original filtering logic according to system settings
        $args['should_log'] = $args['entry']->level <= (int) Settings::get('log_level');

        // store entry
        $this->logEntries[] = $args['entry'];
    }

    /**
     * Make captcha check always succeed
     */
    function onCaptchaCheckAfter(array $args): void
    {
        if (!$this->getConfig()['captcha_check_always_true']) {
            return;
        }

        $args['output'] = true;
    }

    /**
     * Inject custom CSS and JS
     */
    function onHead(array $args): void
    {
        $args['css'][] = $this->getAssetPath('public/devkit.css');
        $args['js'][] = $this->getAssetPath('public/devkit.js');
    }

    /**
     * Render toolbar before </body>
     */
    function onEnd(array $args): void
    {
        $args['output'] .= $this->getToolbar()->render();
    }

    /**
     * Inject toolbar CSS into the error screen
     */
    function onErrorScreenHead(): void
    {
        if (!Core::isReady()) {
            return;
        }

        ?>
<link rel="stylesheet" href="<?= _e($this->getAssetPath('public/devkit.css')) ?>">
<?php
    }

    /**
     * Inject toolbar JS and HTML into the error screen
     */
    function onErrorScreenEnd(): void
    {
        if (!Core::isReady()) {
            return;
        }

        ?>
<script src="<?= _e($this->getAssetPath('public/devkit.js')) ?>"></script>
<?= $this->getToolbar()->render() ?>
<?php
    }

    private function getToolbar(): Component\ToolbarRenderer
    {
        return new Component\ToolbarRenderer(
            $this->sqlLogger->getLog(),
            $this->eventLogger->getLog(),
            $this->missingLocalizationLogger->getMissingEntries(),
            $this->logEntries,
            $this->dumps
        );
    }
}
