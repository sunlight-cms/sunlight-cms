<?php

namespace SunlightExtend\Devkit;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Plugin\PluginData;
use Sunlight\Plugin\PluginManager;
use Kuria\Error\Screen\WebErrorScreen;
use Kuria\Error\Screen\WebErrorScreenEvents;

class DevkitPlugin extends ExtendPlugin
{
    /** @var Component\SqlLogger */
    private $sqlLogger;
    /** @var Component\EventLogger */
    private $eventLogger;
    /** @var Component\MissingLocalizationLogger */
    private $missingLocalizationLogger;
    /** @var array[] */
    private $dumps = [];

    function __construct(PluginData $data, PluginManager $manager)
    {
        parent::__construct($data, $manager);

        $this->sqlLogger = new Component\SqlLogger();
        $this->eventLogger = new Component\EventLogger();
        $this->missingLocalizationLogger = new Component\MissingLocalizationLogger();

        Extend::regGlobal([$this->eventLogger, 'log'], 10000);

        $exceptionHandler = Core::$errorHandler->getErrorScreen();
        if ($exceptionHandler instanceof WebErrorScreen) {
            $exceptionHandler->on(WebErrorScreenEvents::RENDER_DEBUG, [$this->sqlLogger, 'showInDebugScreen']);
        }
    }

    protected function getConfigDefaults(): array
    {
        return [
            'mail_log_enabled' => true,
            'disable_anti_spam' => true,
        ];
    }

    /**
     * @param string $file
     * @param int $line
     * @param string $dump
     */
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
     *
     * @param array $args
     */
    function onDbQueryPost(array $args): void
    {
        $this->sqlLogger->log($args['sql']);
    }

    /**
     * Handle a missing localization entry
     *
     * @param array $args
     */
    function onMissingLocalization(array $args): void
    {
        $this->missingLocalizationLogger->log($args['dict'], $args['key']);
    }

    /**
     * Intercept and log emails
     *
     * @param array $args
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

        file_put_contents(_root . 'mail.log', <<<ENTRY
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

    /**
     * Inject custom CSS and JS
     *
     * @param array $args
     */
    function onHead(array $args): void
    {
        $args['css'][] = $this->getWebPath() . '/Resources/devkit.css';
        $args['js'][] = $this->getWebPath() . '/Resources/devkit.js';
    }

    /**
     * Render toolbar before </body>
     *
     * @param array $args
     */
    function onEnd(array $args): void
    {
        $toolbar = new Component\ToolbarRenderer(
            $this->sqlLogger->getLog(),
            $this->eventLogger->getLog(),
            $this->missingLocalizationLogger->getMissingEntries(),
            $this->dumps
        );

        $args['output'] .= $toolbar->render();
    }
}
