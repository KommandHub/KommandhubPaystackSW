<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Logging;

use Kommandhub\PaystackSW\Setting\Service\Config;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigurableLoggerTest extends TestCase
{
    private MockObject&LoggerInterface $innerLogger;
    private MockObject&Config $config;
    private ConfigurableLogger $configurableLogger;

    protected function setUp(): void
    {
        $this->innerLogger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->configurableLogger = new ConfigurableLogger($this->innerLogger, $this->config);
    }

    public function testLogIsSkippedIfDebuggingIsDisabled(): void
    {
        $this->config->method('getBool')->with('enableDebugging')->willReturn(false);
        $this->innerLogger->expects($this->never())->method('log');

        $this->configurableLogger->info('test message');
    }

    public function testLogIsAllowedIfLevelIsSelected(): void
    {
        $this->config->method('getBool')->with('enableDebugging')->willReturn(true);
        $this->config->method('getArray')->with('logLevels')->willReturn(['info', 'error']);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('info', 'test message', []);

        $this->configurableLogger->info('test message');
    }

    public function testLogIsSkippedIfLevelIsNotSelected(): void
    {
        $this->config->method('getBool')->with('enableDebugging')->willReturn(true);
        $this->config->method('getArray')->with('logLevels')->willReturn(['error']);

        $this->innerLogger->expects($this->never())->method('log');

        $this->configurableLogger->info('test message');
    }

    public function testLogIsAllowedIfNoLevelsAreSelectedButDebuggingIsEnabled(): void
    {
        $this->config->method('getBool')->with('enableDebugging')->willReturn(true);
        $this->config->method('getArray')->with('logLevels')->willReturn([]);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('info', 'test message', []);

        $this->configurableLogger->info('test message');
    }

    public function testAlwaysLoggedLevelsAreLoggedEvenIfDebuggingIsDisabled(): void
    {
        $this->config->method('getBool')->with('enableDebugging')->willReturn(false);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('error', 'error message', []);

        $this->configurableLogger->error('error message');
    }

    public function testLogWithNonScalarLevel(): void
    {
        $this->config->method('getBool')->with('enableDebugging')->willReturn(true);
        $this->config->method('getArray')->with('logLevels')->willReturn(['info']);

        $level = new class() implements \Stringable {
            public function __toString(): string
            {
                return 'info';
            }
        };

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('info', 'test message', []);

        $this->configurableLogger->log($level, 'test message');
    }
}
