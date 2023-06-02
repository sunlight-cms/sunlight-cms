<?php

namespace Sunlight;

use Kuria\Debug\Dumper;
use Kuria\Debug\Exception;
use Kuria\Error\ErrorHandlerEvents;
use Sunlight\Exception\CoreException;
use Sunlight\Log\DatabaseHandler;
use Sunlight\Log\LogEntry;
use Sunlight\Log\LogHandlerInterface;
use Sunlight\Log\LogQuery;
use Sunlight\Util\Environment;
use Sunlight\Util\Json;
use Sunlight\Util\Request;

abstract class Logger
{
    /** @var LogHandlerInterface */
    private static $handler;

    /** Map of log level numbers to log level names */
    const LEVEL_NAMES = [
        self::EMERGENCY => 'emergency',
        self::ALERT => 'alert',
        self::CRITICAL => 'critical',
        self::ERROR => 'error',
        self::WARNING => 'warning',
        self::NOTICE => 'notice',
        self::INFO => 'info',
        self::DEBUG => 'debug',
    ];

    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    private static $initialized = false;

    static function init(): void
    {
        if (self::$initialized) {
            throw new \LogicException('Already initialized');
        }

        Extend::call('logger.init', [
            'handler' => &self::$handler,
        ]);

        if (self::$handler === null) {
            self::$handler = new DatabaseHandler();
        }

        Core::$errorHandler->on(ErrorHandlerEvents::EXCEPTION, function (\Throwable $exception) {
            self::log(
                $exception instanceof CoreException ? self::CRITICAL : self::ERROR,
                'error_handler',
                sprintf('Uncaught %s: %s', Exception::getName($exception), $exception->getMessage()),
                ['exception' => $exception]
            );
        });

        Core::$errorHandler->on(ErrorHandlerEvents::FAILURE, function (\Throwable $exception) {
            self::log(
                self::CRITICAL,
                'error_handler',
                sprintf('Error handling failed: %s', $exception->getMessage()),
                ['exception' => $exception]
            );
        });

        self::$initialized = true;
    }

    static function log(int $level, string $category, string $message, array $context = []): void
    {
        if (!self::$initialized || !isset(self::LEVEL_NAMES[$level])) {
            return;
        }

        $shouldLog = $level <= (int) Settings::get('log_level');

        Extend::call('logger.log.before', [
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => &$context,
            'should_log' => &$shouldLog,
        ]);

        if (!$shouldLog) {
            return;
        }

        try {
            $entry = self::createEntry($level, $category, $message, $context);
            Extend::call('logger.log', ['entry' => $entry]);
            self::$handler->log($entry);
            Extend::call('logger.log.after', ['entry' => $entry]);
        } catch (\Throwable $e) {
            // ignore logger errors
        }
    }

    /**
     * System is unusable
     */
    static function emergency(string $category, string $message, array $context = []): void
    {
        self::log(self::EMERGENCY, $category, $message, $context);
    }

    /**
     * Action must be taken immediately
     */
    static function alert(string $category, string $message, array $context = []): void
    {
        self::log(self::ALERT, $category, $message, $context);
    }

    /**
     * Critical conditions
     */
    static function critical(string $category, string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $category, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically be logged and monitored
     */
    static function error(string $category, string $message, array $context = []): void
    {
        self::log(self::ERROR, $category, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     */
    static function warning(string $category, string $message, array $context = []): void
    {
        self::log(self::WARNING, $category, $message, $context);
    }

    /**
     * Normal but significant events
     */
    static function notice(string $category, string $message, array $context = []): void
    {
        self::log(self::NOTICE, $category, $message, $context);
    }

    /**
     * Interesting events
     */
    static function info(string $category, string $message, array $context = []): void
    {
        self::log(self::INFO, $category, $message, $context);
    }

    /**
     * Detailed debug information
     */
    static function debug(string $category, string $message, array $context = []): void
    {
        self::log(self::DEBUG, $category, $message, $context);
    }

    /**
     * Attempt to retrieve a log entry by ID
     *
     * @param string|int $id
     */
    static function get($id): ?LogEntry
    {
        if (!self::$initialized) {
            return null;
        }

        return self::$handler->get($id);
    }

    /**
     * List entries by the given parameters
     *
     * @return LogEntry[]
     */
    static function search(LogQuery $query): array
    {
        if (!self::$initialized) {
            return [];
        }

        return self::$handler->search($query);
    }

    static function getTotalResults(LogQuery $query): int
    {
        if (!self::$initialized) {
            return 0;
        }

        return self::$handler->getTotalResults($query);
    }

    /**
     * Get distinct categories present in the log
     *
     * @return string[]
     */
    static function getCategories(): array
    {
        if (!self::$initialized) {
            return [];
        }

        return self::$handler->getCategories();
    }

    /**
     * Cleanup the log according to system settings
     */
    static function cleanup(): void
    {
        $retention = Settings::get('log_retention');

        if ($retention !== '') {
            self::$handler->cleanup(time() - (int) $retention * 86400);
        }
    }

    private static function createEntry(int $level, string $category, string $message, array $context): LogEntry
    {
        $entry = new LogEntry();
        $entry->level = $level;
        $entry->category = $category;
        $entry->time = time();
        $entry->message = $message;

        if (!Environment::isCli()) {
            $entry->method = Request::method();
            $entry->url = Core::getCurrentUrl()->build();
            $entry->ip = Core::getClientIp();
            $entry->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }

        $entry->userId = User::isLoggedIn() ? User::getId() : null;

        if (!empty($context)) {
            $entry->context = Json::encode(self::processContext($context));
        }

        return $entry;
    }

    private static function processContext(array $context): array
    {
        $processed = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $processed[$key] = $value;
            } elseif ($value instanceof \Throwable) {
                $processed[$key] = Exception::render($value, true, true);
            } else {
                $processed[$key] = Dumper::dump($value, 3, 255);
            }
        }

        return $processed;
    }
}
