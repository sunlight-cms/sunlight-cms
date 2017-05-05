<?php

namespace SunlightPlugins\Extend\Devkit;

use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Plugin\PluginManager;
use SunlightPlugins\Extend\Devkit\Component\EventLogger;
use SunlightPlugins\Extend\Devkit\Component\SqlLogger;

/**
 * Devkit plugin
 *
 * @author ShiraNai7 <shira.cz>
 */
class DevkitPlugin extends ExtendPlugin
{
    /** @var SqlLogger */
    public $sqlLogger;
    /** @var EventLogger */
    public $eventLogger;

    public function __construct(array $data, PluginManager $manager)
    {
        parent::__construct($data, $manager);

        $this->sqlLogger = new Component\SqlLogger();
        $this->eventLogger = new Component\EventLogger();
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
     * Interecpt and log emails
     *
     * @param array $args
     */
    public function onMail(array $args)
    {
        $time = _formatTime(time());
        $args['handled'] = true;

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
        , FILE_APPEND);
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
        $toolbar = new Component\ToolbarRenderer($this->sqlLogger, $this->eventLogger);

        $args['output'] .= $toolbar->render();
    }
}
