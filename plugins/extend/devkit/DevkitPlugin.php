<?php

namespace SunlightExtend\Devkit;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Plugin\ExtendPlugin;
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

    public function __construct(array $data, PluginManager $manager)
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

    protected function getConfigDefaults()
    {
        return [
            'mail_log_enabled' => true,
        ];
    }

    /**
     * @param string $file
     * @param int $line
     * @param string $dump
     */
    public function addDump($file, $line, $dump)
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
    public function onDbQuery()
    {
        $this->sqlLogger->setTimer();
    }

    /**
     * Handle database query result
     *
     * @param array $args
     */
    public function onDbQueryPost(array $args)
    {
        $this->sqlLogger->log($args['sql']);
    }

    /**
     * Handle a missing localization entry
     *
     * @param array $args
     */
    public function onMissingLocalization(array $args)
    {
        $this->missingLocalizationLogger->log($args['dict'], $args['key']);
    }

    /**
     * Intercept and log emails
     *
     * @param array $args
     */
    public function onMail(array $args)
    {
        if (!$this->getConfig()->offsetGet('mail_log_enabled')) {
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
     * Inject custom CSS and JS
     *
     * @param array $args
     */
    public function onHead(array $args)
    {
        $args['css'][] = $this->getWebPath() . '/Resources/devkit.css';
        $args['js'][] = $this->getWebPath() . '/Resources/devkit.js';
    }

    /**
     * Render toolbar before </body>
     *
     * @param array $args
     */
    public function onEnd(array $args)
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
