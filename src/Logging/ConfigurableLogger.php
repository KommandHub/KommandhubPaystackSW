<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Logging;

use Kommandhub\PaystackSW\Setting\Service\Config;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Configurable logger wrapper.
 *
 * This logger acts as a gatekeeper around the underlying logger and allows
 * log output to be controlled through plugin configuration.
 *
 * Features:
 * - Enables/disables logging entirely via configuration.
 * - Filters log entries by configured PSR-3 log levels.
 * - Falls back to logging all levels when no specific levels are configured
 *   to maintain backwards compatibility.
 */
class ConfigurableLogger extends AbstractLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    /**
     * Logs a message at the specified level.
     *
     * The message is forwarded to the underlying logger only if:
     * - Logging is enabled.
     * - The log level is allowed by configuration.
     *
     * @param mixed $level PSR-3 log level
     * @param string|Stringable $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelString = is_scalar($level) || $level instanceof Stringable ? (string)$level : 'unknown';

        if (!$this->shouldLog($levelString)) {
            return;
        }

        $this->logger->log($levelString, $message, $context);
    }

    /**
     * Severity levels always written, regardless of the debug toggle, so
     * production keeps a trail of failures (webhook signature rejections,
     * verification/refund errors).
     */
    private const ALWAYS_LOGGED = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
    ];

    /**
     * Determines whether a log entry should be written.
     */
    private function shouldLog(string $level): bool
    {
        if (in_array($level, self::ALWAYS_LOGGED, true)) {
            return true;
        }

        return $this->isLoggingEnabled()
            && $this->isLevelAllowed($level);
    }

    /**
     * Determines whether logging is enabled.
     */
    private function isLoggingEnabled(): bool
    {
        return $this->config->getBool('enableDebugging');
    }

    /**
     * Determines whether the given log level is allowed.
     *
     * If no log levels are configured, all levels are considered allowed.
     * This preserves backwards compatibility for installations that have
     * enabled debugging but have not explicitly selected any log levels.
     */
    private function isLevelAllowed(string $level): bool
    {
        $allowedLevels = $this->config->getArray('logLevels');

        return empty($allowedLevels)
            || in_array($level, $allowedLevels, true);
    }
}
