<?php

declare(strict_types=1);

namespace App\Utils;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;

/**
 * Logger factory using Monolog.
 * 
 * Creates channel-specific loggers with rotating file handlers.
 */
class LoggerManager
{
    private static array $loggers = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get or create a logger for the specified channel.
     *
     * @param string $channel Channel name (app, error, queue, auth, sql)
     * @return Logger
     */
    public function getLogger(string $channel = 'app'): Logger
    {
        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }

        $logPath = rtrim($this->config['path'], '/');
        
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $logger = new Logger($channel);

        // Determine log level
        $level = $this->parseLevel($this->config['level'] ?? 'debug');

        // Main handler - rotating file
        $logFile = $logPath . '/' . $channel . '.log';
        $handler = new RotatingFileHandler(
            filename: $logFile,
            maxFiles: 7,
            level: $level,
            filePermission: 0644,
            useLocking: true
        );

        $handler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        ));

        $logger->pushHandler($handler);

        // In debug mode, also log to stderr
        if ($this->config['level'] === 'debug') {
            $streamHandler = new StreamHandler('php://stderr', Level::Debug);
            $streamHandler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message%\n",
                'H:i:s',
                false,
                true
            ));
            $logger->pushHandler($streamHandler);
        }

        self::$loggers[$channel] = $logger;

        return $logger;
    }

    /**
     * Parse string log level to Monolog Level enum.
     *
     * @param string $level
     * @return Level
     */
    private function parseLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Debug,
        };
    }
}
