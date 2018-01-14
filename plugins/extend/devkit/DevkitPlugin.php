<?php

namespace SunlightExtend\Devkit;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Plugin\PluginManager;
use Kuria\Error\Screen\WebErrorScreen;

/**
 * Devkit plugin
 *
 * @author ShiraNai7 <shira.cz>
 */
class DevkitPlugin extends ExtendPlugin
{
    /** @var Component\SqlLogger */
    public $sqlLogger;
    /** @var Component\EventLogger */
    public $eventLogger;
    /** @var Component\MissingLocalizationLogger */
    public $missingLocalizationLogger;

    public function __construct(array $data, PluginManager $manager)
    {
        parent::__construct($data, $manager);

        $this->sqlLogger = new Component\SqlLogger();
        $this->eventLogger = new Component\EventLogger();
        $this->missingLocalizationLogger = new Component\MissingLocalizationLogger();

        Extend::regGlobal(array($this->eventLogger, 'log'), 10000);

        $exceptionHandler = Core::$errorHandler->getExceptionHandler();
        if ($exceptionHandler instanceof WebErrorScreen) {
            $exceptionHandler->on('render.debug', array($this->sqlLogger, 'showInDebugScreen'));
        }
    }

    protected function getConfigDefaults()
    {
        return array(
            'mail_log_enabled' => true,
        );
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

        $time = _formatTime(time());
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
            $this->missingLocalizationLogger->getMissingEntries()
        );

        $args['output'] .= $toolbar->render();
    }
}
