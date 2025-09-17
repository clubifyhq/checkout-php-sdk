<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Logger;

use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Logger PSR-3 simples para o Clubify SDK
 */
class Logger extends AbstractLogger
{
    private ConfigurationInterface $config;
    private array $context = [];

    public function __construct(ConfigurationInterface $config)
    {
        $this->config = $config;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => (string) $message,
            'context' => array_merge($this->context, $context),
        ];

        $this->writeLog($logEntry);
    }

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function child(array $context): self
    {
        $child = new self($this->config);
        $child->context = array_merge($this->context, $context);
        return $child;
    }

    public function setDebugMode(bool $enabled): void
    {
        $this->config->set('logger.level', $enabled ? LogLevel::DEBUG : LogLevel::INFO);
    }

    private function shouldLog(string $level): bool
    {
        $configLevel = $this->config->getLoggerConfig()['level'] ?? 'info';

        $levels = [
            LogLevel::DEBUG => 0,
            LogLevel::INFO => 1,
            LogLevel::NOTICE => 2,
            LogLevel::WARNING => 3,
            LogLevel::ERROR => 4,
            LogLevel::CRITICAL => 5,
            LogLevel::ALERT => 6,
            LogLevel::EMERGENCY => 7,
        ];

        return ($levels[$level] ?? 1) >= ($levels[$configLevel] ?? 1);
    }

    private function writeLog(array $logEntry): void
    {
        $formatted = json_encode($logEntry);
        error_log($formatted);
    }
}